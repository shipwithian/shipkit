<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ShipKit\Core\Architecture\ProjectLayout;

/**
 * A scratch project holding the given files, read through its own layout.
 *
 * @param  array<string, string>  $files
 */
function layoutOfScratchProject(array $files): ProjectLayout
{
    $basePath = sys_get_temp_dir().'/shipkit-standard-folders-'.Str::uuid();
    $filesystem = new Filesystem;

    $files['composer.json'] = json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]);

    foreach ($files as $path => $contents) {
        $filesystem->ensureDirectoryExists(dirname("{$basePath}/{$path}"));
        $filesystem->put("{$basePath}/{$path}", $contents);
    }

    return new ProjectLayout($basePath);
}

function scratchClass(string $namespace, string $declaration): string
{
    return "<?php\n\nnamespace {$namespace};\n\n{$declaration}\n";
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/shipkit-standard-folders-*', GLOB_ONLYDIR) ?: [] as $path) {
        (new Filesystem)->deleteDirectory($path);
    }
});

it('accepts each kind of class in its standard folder', function () {
    $layout = layoutOfScratchProject([
        'app/Actions/Billing/CreateInvoiceAction.php' => scratchClass('App\Actions\Billing', 'class CreateInvoiceAction { public function handle(): void {} }'),
        'app/Actions/Fortify/CreateNewUser.php' => scratchClass('App\Actions\Fortify', 'class CreateNewUser {}'),
        'app/Queries/Billing/InvoiceListQuery.php' => scratchClass('App\Queries\Billing', 'class InvoiceListQuery { public function handle(): void {} }'),
        'app/Models/Invoice.php' => scratchClass('App\Models', 'use Illuminate\Database\Eloquent\Model; class Invoice extends Model {}'),
    ]);

    expect($layout->misplacedClasses())->toBeEmpty();
});

it('fails on an app/Domain folder', function () {
    $layout = layoutOfScratchProject([
        'app/Domain/Billing/Actions/CreateInvoiceAction.php' => scratchClass('App\Domain\Billing\Actions', 'class CreateInvoiceAction {}'),
    ]);

    expect($layout->misplacedClasses())
        ->toContain("app/Domain exists. ShipKit uses Laravel's standard folders: Actions in app/Actions/{Feature}, Queries in app/Queries/{Feature}, models in app/Models, policies in app/Policies, exceptions in app/Exceptions")
        ->toContain('app/Domain/Billing/Actions/CreateInvoiceAction.php: an Action lives in app/Actions/{Feature}');
});

it('fails on an Action or Query outside its feature folder', function () {
    $layout = layoutOfScratchProject([
        'app/Services/CreateInvoiceAction.php' => scratchClass('App\Services', 'class CreateInvoiceAction {}'),
        'app/Http/InvoiceListQuery.php' => scratchClass('App\Http', 'class InvoiceListQuery {}'),
        'app/Actions/CreateInvoiceAction.php' => scratchClass('App\Actions', 'class CreateInvoiceAction {}'),
    ]);

    expect($layout->misplacedClasses())->toBe([
        'app/Actions/CreateInvoiceAction.php: group it by feature, in app/Actions/{Feature}',
        'app/Http/InvoiceListQuery.php: a Query lives in app/Queries/{Feature}',
        'app/Services/CreateInvoiceAction.php: an Action lives in app/Actions/{Feature}',
    ]);
});

it('fails on an Eloquent model outside app/Models', function () {
    $layout = layoutOfScratchProject([
        'app/Billing/Invoice.php' => scratchClass('App\Billing', 'use Illuminate\Database\Eloquent\Model; class Invoice extends Model {}'),
        'app/Billing/InvoiceLine.php' => scratchClass('App\Billing', 'use Illuminate\Database\Eloquent\Relations\Pivot; class InvoiceLine extends Pivot {}'),
    ]);

    expect($layout->misplacedClasses())->toBe([
        'app/Billing/Invoice.php: an Eloquent model lives in app/Models',
        'app/Billing/InvoiceLine.php: an Eloquent model lives in app/Models',
    ]);
});

it('fails on a Query directly in app/Queries', function () {
    $layout = layoutOfScratchProject([
        'app/Queries/InvoiceListQuery.php' => scratchClass('App\Queries', 'class InvoiceListQuery {}'),
    ]);

    expect($layout->misplacedClasses())->toBe([
        'app/Queries/InvoiceListQuery.php: group it by feature, in app/Queries/{Feature}',
    ]);
});

it('recognises morph pivots and authenticatable users as models', function () {
    $layout = layoutOfScratchProject([
        'app/Billing/Taggable.php' => scratchClass('App\Billing', 'use Illuminate\Database\Eloquent\Relations\MorphPivot; class Taggable extends MorphPivot {}'),
        'app/Billing/Customer.php' => scratchClass('App\Billing', 'use Illuminate\Foundation\Auth\User as Authenticatable; class Customer extends Authenticatable {}'),
        'app/Models/Billing/Invoice.php' => scratchClass('App\Models\Billing', 'use Illuminate\Database\Eloquent\Model; class Invoice extends Model {}'),
    ]);

    expect($layout->misplacedClasses())->toBe([
        'app/Billing/Customer.php: an Eloquent model lives in app/Models',
        'app/Billing/Taggable.php: an Eloquent model lives in app/Models',
    ]);
});

it('discovers features from app/Actions and app/Queries, but not Fortify', function () {
    $layout = layoutOfScratchProject([
        'app/Actions/Fortify/CreateNewUser.php' => scratchClass('App\Actions\Fortify', 'class CreateNewUser {}'),
        'app/Actions/Billing/CreateInvoiceAction.php' => scratchClass('App\Actions\Billing', 'class CreateInvoiceAction {}'),
        'app/Queries/Billing/InvoiceListQuery.php' => scratchClass('App\Queries\Billing', 'class InvoiceListQuery {}'),
        'app/Queries/Reports/RevenueQuery.php' => scratchClass('App\Queries\Reports', 'class RevenueQuery {}'),
    ]);

    expect($layout->features())->toBe(['Billing', 'Reports'])
        ->and($layout->featureNamespaces('Actions'))->toBe(['App\Actions\Billing'])
        ->and($layout->featureNamespaces('Queries'))->toBe(['App\Queries\Billing', 'App\Queries\Reports']);
});
