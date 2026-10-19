<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

/**
 * A model that skips GuardsInvariants, so its invariants could be bypassed.
 */
class UnguardedVerifyRole extends Model {}

afterEach(function () {
    if (str_starts_with(base_path(), sys_get_temp_dir().'/shipkit-verify-')) {
        File::deleteDirectory(base_path());
    }
});

/**
 * Point the application at a scratch project holding the given files.
 *
 * @param  array<string, string>  $files
 */
function verifyInScratchProject(array $files): void
{
    $basePath = sys_get_temp_dir().'/shipkit-verify-'.bin2hex(random_bytes(6));

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$basePath}/{$path}"));
        File::put("{$basePath}/{$path}", $contents);
    }

    app()->setBasePath($basePath);
}

function phpunitXml(bool $withShipKitSuite): string
{
    $suite = $withShipKitSuite
        ? '<testsuite name="ShipKit"><directory>vendor/shipwithian/shipkit-core/tests</directory></testsuite>'
        : '';

    return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit>
            <testsuites>
                <testsuite name="Feature"><directory>tests/Feature</directory></testsuite>
                {$suite}
            </testsuites>
        </phpunit>
        XML;
}

it('passes for a project that registers the checks and maps guarded models', function () {
    $this->artisan('shipkit:verify')->assertSuccessful();
});

it('accepts the testsuite written relative to the current directory', function () {
    verifyInScratchProject([
        'phpunit.xml' => str_replace('<directory>vendor/', '<directory>./vendor/', phpunitXml(withShipKitSuite: true)).PHP_EOL,
        'config/shipkit.php' => '<?php return [];',
    ]);

    $this->artisan('shipkit:verify')->assertSuccessful();
});

it('fails when the project does not run the ShipKit testsuite', function () {
    verifyInScratchProject([
        'phpunit.xml' => phpunitXml(withShipKitSuite: false),
        'config/shipkit.php' => '<?php return [];',
    ]);

    $this->artisan('shipkit:verify')
        ->expectsOutputToContain('vendor/shipwithian/shipkit-core/tests')
        ->assertFailed();
});

it('fails when the project has no ShipKit config', function () {
    verifyInScratchProject([
        'phpunit.xml' => phpunitXml(withShipKitSuite: true),
    ]);

    $this->artisan('shipkit:verify')
        ->expectsOutputToContain('config/shipkit.php')
        ->assertFailed();
});

it('fails when a guarded model is not mapped', function () {
    config(['shipkit.models.permission' => null]);

    $this->artisan('shipkit:verify')
        ->expectsOutputToContain('shipkit.models.permission')
        ->assertFailed();
});

it('fails when a mapped model does not use GuardsInvariants', function () {
    config(['shipkit.models.role' => UnguardedVerifyRole::class]);

    $this->artisan('shipkit:verify')
        ->expectsOutputToContain(UnguardedVerifyRole::class)
        ->assertFailed();
});
