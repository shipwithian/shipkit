<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function () {
    $this->projectPath = base_path();
    $this->scratchPath = sys_get_temp_dir().'/shipkit-make-feature-'.Str::uuid();

    $files = new Filesystem;
    $files->ensureDirectoryExists($this->scratchPath.'/.ai/rules');
    $files->copy(base_path('.ai/rules/index.md'), $this->scratchPath.'/.ai/rules/index.md');

    app()->setBasePath($this->scratchPath);
});

afterEach(function () {
    app()->setBasePath($this->projectPath);

    (new Filesystem)->deleteDirectory($this->scratchPath);
});

it('rejects a name that is not StudlyCase', function () {
    $this->artisan('make:feature', ['name' => 'billing-accounts'])
        ->expectsOutputToContain('Did you mean [BillingAccounts]?')
        ->assertFailed();

    expect(is_dir($this->scratchPath.'/app/Actions'))->toBeFalse();
});

it("refuses a folder that holds a package's own actions", function () {
    $this->artisan('make:feature', ['name' => 'Fortify'])
        ->expectsOutputToContain("package's own actions")
        ->assertFailed();

    expect(is_dir($this->scratchPath.'/app/Actions/Fortify'))->toBeFalse();
});

it('refuses to overwrite an existing feature', function (string $existing) {
    (new Filesystem)->ensureDirectoryExists($this->scratchPath."/{$existing}");

    $this->artisan('make:feature', ['name' => 'Billing'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();
})->with(['app/Actions/Billing', 'app/Queries/Billing']);

it('scaffolds the Actions, Queries, and feature test folders and the rule file', function () {
    $this->artisan('make:feature', ['name' => 'BillingAccounts'])->assertSuccessful();

    foreach (['app/Actions', 'app/Queries', 'tests/Feature'] as $folder) {
        expect($this->scratchPath."/{$folder}/BillingAccounts/.gitkeep")->toBeFile();
    }

    $rules = file_get_contents($this->scratchPath.'/.ai/rules/features/billing-accounts.md');

    expect($rules)
        ->toContain("    - 'app/Actions/BillingAccounts/**'")
        ->toContain("    - 'app/Queries/BillingAccounts/**'")
        ->toContain('# Billing Accounts')
        ->not->toContain('{{');
});

it('creates the MCP tool folder, rule path, and index glob only with --mcp', function () {
    $this->artisan('make:feature', ['name' => 'Billing'])->assertSuccessful();
    $this->artisan('make:feature', ['name' => 'Orders', '--mcp' => true])->assertSuccessful();

    $index = file_get_contents($this->scratchPath.'/.ai/rules/index.md');

    expect(is_dir($this->scratchPath.'/app/Mcp/Tools/Billing'))->toBeFalse()
        ->and($this->scratchPath.'/app/Mcp/Tools/Orders/.gitkeep')->toBeFile()
        ->and(file_get_contents($this->scratchPath.'/.ai/rules/features/billing.md'))->not->toContain('app/Mcp/Tools')
        ->and(file_get_contents($this->scratchPath.'/.ai/rules/features/orders.md'))->toContain("    - 'app/Mcp/Tools/Orders/**'")
        ->and($index)->toContain('app/Actions/Orders/**, app/Queries/Orders/**, app/Mcp/Tools/Orders/**')
        ->and($index)->not->toContain('app/Mcp/Tools/Billing');
});

it('adds MCP tools to a feature that already exists, once', function () {
    $this->artisan('make:feature', ['name' => 'Billing'])->assertSuccessful();

    $this->artisan('make:feature', ['name' => 'Billing', '--mcp' => true])
        ->expectsOutputToContain('Added MCP tools to the Billing feature.')
        ->assertSuccessful();

    expect($this->scratchPath.'/app/Mcp/Tools/Billing/.gitkeep')->toBeFile()
        ->and(file_get_contents($this->scratchPath.'/.ai/rules/features/billing.md'))->toContain("    - 'app/Mcp/Tools/Billing/**'")
        ->and(file_get_contents($this->scratchPath.'/.ai/rules/index.md'))->toContain('app/Actions/Billing/**, app/Queries/Billing/**, app/Mcp/Tools/Billing/**');

    $this->artisan('make:feature', ['name' => 'Billing', '--mcp' => true])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(substr_count(file_get_contents($this->scratchPath.'/.ai/rules/features/billing.md'), 'app/Mcp/Tools/Billing'))->toBe(1);
});

it('points a feature without tools at --mcp instead of recreating it', function () {
    $this->artisan('make:feature', ['name' => 'Billing'])->assertSuccessful();

    $this->artisan('make:feature', ['name' => 'Billing'])
        ->expectsOutputToContain('make:feature Billing --mcp')
        ->assertFailed();
});
it('adds the index row after the existing feature rows', function () {
    $this->artisan('make:feature', ['name' => 'Billing'])->assertSuccessful();

    $lines = explode("\n", file_get_contents($this->scratchPath.'/.ai/rules/index.md'));
    $featureRows = array_values(array_filter($lines, fn (string $line): bool => str_contains($line, '.ai/rules/features/')));

    expect(end($featureRows))
        ->toStartWith('| app/Actions/Billing/**, app/Queries/Billing/**')
        ->toContain('| .ai/rules/features/billing.md');

    $header = collect($lines)->first(fn (string $line): bool => str_starts_with($line, '| Applies to'));

    expect(strlen(end($featureRows)))->toBe(strlen($header));
});
