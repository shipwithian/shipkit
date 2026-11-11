<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ShipKit\Core\Architecture\ProjectLayout;

/**
 * A tool that follows every convention. Each case below breaks exactly one of them.
 */
const VALID_TOOL = <<<'PHP'
<?php

namespace App\Mcp\Tools\Billing;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_invoices')]
#[Title('List invoices')]
#[IsReadOnly]
class ListInvoicesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('viewAny', Invoice::class);

        return Response::structured(['invoices' => []]);
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [];
    }
}
PHP;

/**
 * A scratch project with the given files, read through its own layout. A tool at app/Mcp/Tools/Billing
 * is registered in a server unless the server source is given.
 *
 * @param  array<string, string>  $files
 */
function layoutOf(array $files, ?string $server = null): ProjectLayout
{
    $basePath = sys_get_temp_dir().'/shipkit-mcp-tools-'.Str::uuid();
    $filesystem = new Filesystem;

    $files['composer.json'] = (string) json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]);

    foreach ($files as $path => $contents) {
        $filesystem->ensureDirectoryExists(dirname("{$basePath}/{$path}"));
        $filesystem->put("{$basePath}/{$path}", $contents);
    }

    $registered = collect($files)->filter(fn (string $contents, string $path): bool => str_starts_with($path, 'app/Mcp/Tools/'))
        ->map(fn (string $contents): string => preg_match('/\bclass\s+(\w+)/', $contents, $class) ? "{$class[1]}::class" : '')
        ->implode(', ');

    $filesystem->ensureDirectoryExists("{$basePath}/app/Mcp/Servers");
    $filesystem->put("{$basePath}/app/Mcp/Servers/AppServer.php", $server ?? "<?php\n\nclass AppServer { protected array \$tools = [{$registered}]; }\n");

    return new ProjectLayout($basePath);
}

/**
 * The valid tool with one change, checked for the violations it causes.
 *
 * @return list<string>
 */
function violationsAfter(string $search, string $replace, ?string $server = null): array
{
    expect(VALID_TOOL)->toContain($search);

    return layoutOf(['app/Mcp/Tools/Billing/ListInvoicesTool.php' => str_replace($search, $replace, VALID_TOOL)], $server)->toolConventionViolations();
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/shipkit-mcp-tools-*', GLOB_ONLYDIR) ?: [] as $path) {
        (new Filesystem)->deleteDirectory($path);
    }
});

it('accepts a tool that follows every convention', function () {
    $layout = layoutOf(['app/Mcp/Tools/Billing/ListInvoicesTool.php' => VALID_TOOL]);

    expect($layout->toolConventionViolations())->toBe([])
        ->and($layout->misplacedClasses())->toBe([]);
});

it('accepts a tool that authorizes with nothing before it', function () {
    expect(violationsAfter("        \$user = \$request->user();\n\n        Gate::forUser(\$user)", '        Gate::forUser($request->user())'))->toBe([]);
});

it('fails on a tool class that is not named {Name}Tool', function () {
    expect(violationsAfter('class ListInvoicesTool', 'class ListInvoices'))->toBe(['app/Mcp/Tools/Billing/ListInvoicesTool.php: name the tool class ListInvoicesTool']);
});

it('fails on a missing or non-snake_case tool name', function (string $replace) {
    expect(violationsAfter("#[Name('list_invoices')]\n", $replace))->toBe(["app/Mcp/Tools/Billing/ListInvoicesTool.php: declare a snake_case #[Name('show_my_profile')], so AI clients see a stable tool name"]);
})->with(['no name' => [''], 'spaces' => ["#[Name('List invoices')]\n"], 'camel case' => ["#[Name('listInvoices')]\n"]]);

it('fails on a tool without a title', function () {
    expect(violationsAfter("#[Title('List invoices')]\n", ''))->toBe(["app/Mcp/Tools/Billing/ListInvoicesTool.php: declare a #[Title('Show my profile')] for people reading the tool list"]);
});

it('fails on a tool that is neither read-only nor destructive', function () {
    expect(violationsAfter("#[IsReadOnly]\n", ''))->toBe(['app/Mcp/Tools/Billing/ListInvoicesTool.php: declare #[IsReadOnly] or #[IsDestructive(true|false)] explicitly; MCP clients treat a tool with neither as destructive']);
});

it('fails on a tool no server registers', function () {
    expect(violationsAfter('#[Name', '#[Name', "<?php\n\nclass AppServer { protected array \$tools = []; }\n"))->toBe(['app/Mcp/Tools/Billing/ListInvoicesTool.php: register ListInvoicesTool in a server under app/Mcp/Servers, or no AI client can call it']);
});

it('fails on a tool that does not authorize first', function (string $search, string $replace, string $violation) {
    expect(violationsAfter($search, $replace))->toBe(["app/Mcp/Tools/Billing/ListInvoicesTool.php: {$violation}"]);
})->with([
    'no authorization' => ["        Gate::forUser(\$user)->authorize('viewAny', Invoice::class);\n\n", '', 'call Gate::forUser($user)->authorize() in handle(); Gate::inspect() never throws and a plain Gate::authorize() does not name the user'],
    'Gate::inspect, which never throws' => ['Gate::forUser($user)->authorize(', 'Gate::forUser($user)->inspect(', 'call Gate::forUser($user)->authorize() in handle(); Gate::inspect() never throws and a plain Gate::authorize() does not name the user'],
    'plain Gate::authorize' => ['Gate::forUser($user)->authorize(', 'Gate::authorize(', 'call Gate::forUser($user)->authorize() in handle(); Gate::inspect() never throws and a plain Gate::authorize() does not name the user'],
    'validating first' => ['        Gate::forUser($user)', "        \$request->validate([]);\n        Gate::forUser(\$user)", 'authorize before anything else in handle(); only `$user = $request->user();` may come first'],
    'reading first' => ['        Gate::forUser($user)', "        \$invoices = Invoice::all();\n        Gate::forUser(\$user)", 'authorize before anything else in handle(); only `$user = $request->user();` may come first'],
]);
it('does not count a comment as authorization', function () {
    expect(violationsAfter("        Gate::forUser(\$user)->authorize('viewAny', Invoice::class);\n", "        // Gate::forUser(\$user)->authorize('viewAny', Invoice::class);\n"))
        ->toBe(['app/Mcp/Tools/Billing/ListInvoicesTool.php: call Gate::forUser($user)->authorize() in handle(); Gate::inspect() never throws and a plain Gate::authorize() does not name the user']);
});

it('fails on a tool that uses DB, by call or by import', function (string $search, string $replace) {
    expect(violationsAfter($search, $replace))->toBe(['app/Mcp/Tools/Billing/ListInvoicesTool.php: uses DB; read through a Query object and write through an Action']);
})->with([
    'a call' => ["        return Response::structured(['invoices' => []]);", "        return Response::structured(['invoices' => \\Illuminate\\Support\\Facades\\DB::table('invoices')->get()]);"],
    'an import' => ["use Illuminate\\Support\\Facades\\Gate;\n", "use Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Gate;\n"],
]);

it('fails on a tool that queries a model, imported or fully qualified', function (array $imports, string $query, string $expected) {
    $source = str_replace(
        ["use Illuminate\\Support\\Facades\\Gate;\n", "        return Response::structured(['invoices' => []]);"],
        ["use Illuminate\\Support\\Facades\\Gate;\n".$imports[0], "        return Response::structured(['invoices' => {$query}->get()]);"],
        VALID_TOOL,
    );

    expect(layoutOf(['app/Mcp/Tools/Billing/ListInvoicesTool.php' => $source])->toolConventionViolations())
        ->toBe(["app/Mcp/Tools/Billing/ListInvoicesTool.php: {$expected}; read through a Query object and write through an Action"]);
})->with([
    'an imported model' => [["use Spatie\\Permission\\Models\\Role;\n"], 'Role::query()', 'Role::query queries the Role model'],
    'a fully qualified model' => [[''], '\\App\\Models\\Invoice::query()', '\\App\\Models\\Invoice::query queries a model'],
]);
it('fails on a tool without an output schema', function () {
    expect(violationsAfter("    public function outputSchema(JsonSchema \$schema): array\n    {\n        return [];\n    }\n", ''))->toBe(['app/Mcp/Tools/Billing/ListInvoicesTool.php: declare outputSchema() so AI clients can parse the result']);
});

it('fails on a destructive tool that does not confirm before acting', function (string $handleBody, string $confirmationImport) {
    $source = str_replace(
        ["#[IsReadOnly]\n", "    public function handle(Request \$request): Response\n", "        return Response::structured(['invoices' => []]);"],
        ["#[IsDestructive]\n", "    public function handle(Request \$request{$confirmationImport}): Response\n", $handleBody],
        VALID_TOOL,
    );

    expect(layoutOf(['app/Mcp/Tools/Billing/ListInvoicesTool.php' => $source])->toolConventionViolations())
        ->toBe(['app/Mcp/Tools/Billing/ListInvoicesTool.php: a destructive tool confirms before acting; type-hint ConsequentialActionConfirmation in handle() and run the action through confirmBeforeActing()']);
})->with([
    'neither the type-hint nor the call' => ['        return Response::structured([]);', ''],
    'the type-hint without the call' => ['        return Response::structured([]);', ', ConsequentialActionConfirmation $confirmation'],
    'the call without the type-hint' => ["        return \$confirmation->confirmBeforeActing(\$request, self::class, [], fn () => 'Pay', fn () => []);", ''],
    'both only in a comment' => ["        // ConsequentialActionConfirmation ->confirmBeforeActing(\n        return Response::structured([]);", ''],
]);

it('accepts a destructive tool that confirms, and a tool that says it is not destructive', function () {
    $confirmed = str_replace(
        ["#[IsReadOnly]\n", "    public function handle(Request \$request): Response\n", "        return Response::structured(['invoices' => []]);"],
        ["#[IsDestructive]\n", "    public function handle(Request \$request, ConsequentialActionConfirmation \$confirmation): Response\n", "        return \$confirmation->confirmBeforeActing(\$request, self::class, [], fn () => 'Pay', fn () => []);"],
        VALID_TOOL,
    );

    expect(layoutOf(['app/Mcp/Tools/Billing/ListInvoicesTool.php' => $confirmed])->toolConventionViolations())->toBe([])
        ->and(violationsAfter('#[IsReadOnly]', '#[IsDestructive(false)]'))->toBe([]);
});

it('keeps tools in app/Mcp/Tools, grouped by feature, whatever their file is named', function () {
    $layout = layoutOf([
        'app/Http/PayInvoice.php' => "<?php\n\nclass PayInvoice extends \\Laravel\\Mcp\\Server\\Tool {}\n",
        'app/Mcp/Tools/ListInvoicesTool.php' => "<?php\n\nclass ListInvoicesTool {}\n",
        'app/Mcp/Tools/Billing/ListInvoicesTool.php' => VALID_TOOL,
    ]);

    expect($layout->misplacedClasses())->toBe([
        'app/Http/PayInvoice.php: an MCP tool lives in app/Mcp/Tools/{Feature}',
        'app/Mcp/Tools/ListInvoicesTool.php: group it by feature, in app/Mcp/Tools/{Feature}',
    ]);
});
