<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function () {
    $this->projectPath = base_path();
    $this->scratchPath = sys_get_temp_dir().'/shipkit-make-context-'.Str::uuid();

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
    $this->artisan('make:context', ['name' => 'billing-accounts'])
        ->expectsOutputToContain('Did you mean [BillingAccounts]?')
        ->assertFailed();

    expect(is_dir($this->scratchPath.'/app/Domain'))->toBeFalse();
});

it('refuses to treat the shared kernel as a context', function () {
    $this->artisan('make:context', ['name' => 'Shared'])
        ->expectsOutputToContain('shipwithian/shipkit-core')
        ->assertFailed();

    expect(is_dir($this->scratchPath.'/app/Domain/Shared'))->toBeFalse();
});

it('refuses to overwrite an existing context', function () {
    (new Filesystem)->ensureDirectoryExists($this->scratchPath.'/app/Domain/Billing');

    $this->artisan('make:context', ['name' => 'Billing'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();
});

it('scaffolds the domain folders, rule file, index row, and feature test folder', function () {
    $this->artisan('make:context', ['name' => 'BillingAccounts'])->assertSuccessful();

    foreach (['Actions', 'Queries', 'Models', 'Exceptions', 'Policies'] as $folder) {
        expect($this->scratchPath."/app/Domain/BillingAccounts/{$folder}/.gitkeep")->toBeFile();
    }

    expect($this->scratchPath.'/tests/Feature/BillingAccounts/.gitkeep')->toBeFile();

    $rules = file_get_contents($this->scratchPath.'/.ai/rules/contexts/billing-accounts.md');

    expect($rules)
        ->toContain("    - 'app/Domain/BillingAccounts/**'")
        ->toContain('# Billing Accounts context')
        ->not->toContain('{{');
});

it('adds the index row after the existing context rows', function () {
    $this->artisan('make:context', ['name' => 'Billing'])->assertSuccessful();

    $lines = explode("\n", file_get_contents($this->scratchPath.'/.ai/rules/index.md'));
    $contextRows = array_values(array_filter($lines, fn (string $line): bool => str_contains($line, '.ai/rules/contexts/')));

    expect(end($contextRows))
        ->toStartWith('| app/Domain/Billing/**')
        ->toContain('| .ai/rules/contexts/billing.md');

    $header = collect($lines)->first(fn (string $line): bool => str_starts_with($line, '| Applies to'));

    expect(strlen(end($contextRows)))->toBe(strlen($header));
});
