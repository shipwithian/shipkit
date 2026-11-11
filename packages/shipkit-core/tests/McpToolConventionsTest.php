<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ShipKit\Core\Architecture\ProjectLayout;

/**
 * A scratch project holding one tool at app/Mcp/Tools/Billing/{Name}Tool.php.
 */
function layoutWithTool(string $name, string $body, string $imports = '', string $attributes = '', string $traits = ''): ProjectLayout
{
    $basePath = sys_get_temp_dir().'/shipkit-mcp-tools-'.Str::uuid();
    $files = new Filesystem;
    $files->ensureDirectoryExists("{$basePath}/app/Mcp/Tools/Billing");
    $files->put("{$basePath}/composer.json", (string) json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    $files->put("{$basePath}/app/Mcp/Tools/Billing/{$name}Tool.php", <<<PHP
        <?php

        namespace App\Mcp\Tools\Billing;

        use Illuminate\Support\Facades\Gate;
        {$imports}

        {$attributes}
        class {$name}Tool extends Tool
        {
            {$traits}

            public function handle(Request \$request): Response
            {
        {$body}
            }
        }
        PHP);

    return new ProjectLayout($basePath);
}

function outputSchemaMethod(): string
{
    return "\n    public function outputSchema(JsonSchema \$schema): array\n    {\n        return [];\n    }";
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/shipkit-mcp-tools-*', GLOB_ONLYDIR) ?: [] as $path) {
        (new Filesystem)->deleteDirectory($path);
    }
});

it('accepts a tool that authorizes first, uses a Query, and declares its output', function () {
    $layout = layoutWithTool('ListInvoices', "        Gate::forUser(\$request->user())->authorize('viewAny', Invoice::class);\n\n        return Response::structured(['invoices' => \$this->invoices->handle()]);\n    }".outputSchemaMethod()."\n    private function unused(): void\n    {");

    expect($layout->toolConventionViolations())->toBe([]);
});

it('fails on a tool that does not authorize, or authorizes after acting', function () {
    $missing = layoutWithTool('ListInvoices', "        return Response::structured(\$this->invoices->handle());\n    }".outputSchemaMethod()."\n    private function unused(): void\n    {");
    $late = layoutWithTool('PayInvoice', "        \$this->payInvoice->handle(\$request->get('id'));\n        Gate::forUser(\$request->user())->authorize('pay', Invoice::class);\n\n        return Response::text('Paid.');\n    }".outputSchemaMethod()."\n    private function unused(): void\n    {");

    expect($missing->toolConventionViolations())->toBe(['app/Mcp/Tools/Billing/ListInvoicesTool.php: call Gate::forUser($user)->authorize() first in handle(), before validating, reading, or writing'])
        ->and($late->toolConventionViolations())->toBe(['app/Mcp/Tools/Billing/PayInvoiceTool.php: call Gate::forUser($user)->authorize() first in handle(), before validating, reading, or writing']);
});

it('fails on a tool that uses DB or queries a model directly', function () {
    $layout = layoutWithTool(
        'ListUsers',
        "        Gate::forUser(\$request->user())->authorize('viewAny', User::class);\n\n        return Response::structured(['users' => User::query()->get(), 'count' => DB::table('users')->count()]);\n    }".outputSchemaMethod()."\n    private function unused(): void\n    {",
        "use App\\Models\\User;\nuse Illuminate\\Support\\Facades\\DB;",
    );

    expect($layout->toolConventionViolations())->toBe([
        'app/Mcp/Tools/Billing/ListUsersTool.php: uses DB; read through a Query object and write through an Action',
        'app/Mcp/Tools/Billing/ListUsersTool.php: User::query queries the User model; read through a Query object and write through an Action',
    ]);
});

it('fails on a tool without an output schema', function () {
    $layout = layoutWithTool('ShowInvoice', "        Gate::forUser(\$request->user())->authorize('view', \$invoice);\n\n        return Response::text('Invoice');");

    expect($layout->toolConventionViolations())->toBe(['app/Mcp/Tools/Billing/ShowInvoiceTool.php: declare outputSchema() so AI clients can parse the result']);
});

it('fails on a destructive tool that does not confirm before acting', function () {
    $body = "        Gate::forUser(\$request->user())->authorize('pay', Invoice::class);\n\n        return Response::text('Paid.');\n    }".outputSchemaMethod()."\n    private function unused(): void\n    {";

    $unconfirmed = layoutWithTool('PayInvoice', $body, attributes: '#[IsDestructive]');
    $confirmedBody = "        Gate::forUser(\$request->user())->authorize('refund', Invoice::class);\n\n        return \$confirmation->confirmBeforeActing(\$request, self::class, [], fn () => 'Refund', fn () => []);\n    }".outputSchemaMethod()."\n    private function unused(): void\n    {";
    $confirmed = layoutWithTool('RefundInvoice', $confirmedBody, 'use App\\Mcp\\Concerns\\ConsequentialActionConfirmation;', '#[IsDestructive]');
    $notDestructive = layoutWithTool('NoteInvoice', $body, attributes: '#[IsDestructive(false)]');

    expect($unconfirmed->toolConventionViolations())->toBe(['app/Mcp/Tools/Billing/PayInvoiceTool.php: a destructive tool confirms before acting; type-hint ConsequentialActionConfirmation in handle() and run the action through confirmBeforeActing()'])
        ->and($confirmed->toolConventionViolations())->toBe([])
        ->and($notDestructive->toolConventionViolations())->toBe([]);
});

it('keeps tools in app/Mcp/Tools, grouped by feature', function () {
    $basePath = sys_get_temp_dir().'/shipkit-mcp-tools-'.Str::uuid();
    $files = new Filesystem;
    $files->put(tap("{$basePath}/composer.json", fn (string $path) => $files->ensureDirectoryExists(dirname($path))), (string) json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));

    foreach (['app/Http/PayInvoiceTool.php', 'app/Mcp/Tools/ListInvoicesTool.php'] as $path) {
        $files->ensureDirectoryExists(dirname("{$basePath}/{$path}"));
        $files->put("{$basePath}/{$path}", "<?php\n\nclass ".basename($path, '.php')." {}\n");
    }

    expect((new ProjectLayout($basePath))->misplacedClasses())->toBe([
        'app/Http/PayInvoiceTool.php: an MCP tool lives in app/Mcp/Tools/{Feature}',
        'app/Mcp/Tools/ListInvoicesTool.php: group it by feature, in app/Mcp/Tools/{Feature}',
    ]);
});
