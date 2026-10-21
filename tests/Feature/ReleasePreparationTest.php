<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function () {
    foreach (['git', 'jq'] as $tool) {
        if ((new Process(['which', $tool]))->run() !== 0) {
            $this->markTestSkipped("The release script needs {$tool}.");
        }
    }

    if (! file_exists(base_path('.git'))) {
        $this->markTestSkipped('This is not a git checkout, so there is no release to prepare.');
    }

    $this->checkout = sys_get_temp_dir().'/shipkit-release-'.Str::uuid();
});

afterEach(function () {
    File::deleteDirectory($this->checkout);
});

describe('a prepared release', function () {
    beforeEach(function () {
        commitCheckoutOfProject($this->checkout);
        $this->main = runInCheckout($this->checkout, ['git', 'rev-parse', 'HEAD']);

        releaseScript('1.4.2', $this->checkout)->mustRun();
    });

    it('requires the published shipkit-core instead of the path repository', function () {
        $composer = json_decode((string) file_get_contents($this->checkout.'/composer.json'), true);

        expect($composer['require']['shipwithian/shipkit-core'])->toBe('^1.4')
            ->and($composer)->not->toHaveKey('repositories')
            ->and(is_dir($this->checkout.'/packages'))->toBeFalse();
    });

    it('tags one commit on top of main and leaves nothing uncommitted', function () {
        expect(runInCheckout($this->checkout, ['git', 'rev-parse', 'v1.4.2^{commit}']))->toBe(runInCheckout($this->checkout, ['git', 'rev-parse', 'HEAD']))
            ->and(runInCheckout($this->checkout, ['git', 'rev-parse', 'HEAD~1']))->toBe($this->main)
            ->and(runInCheckout($this->checkout, ['git', 'status', '--porcelain']))->toBe('');
    });

    it('removes the package folder from the checks that listed it and keeps the rest', function () {
        foreach (['phpstan.neon', 'rector.php', 'phpunit.xml'] as $configuration) {
            expect((string) file_get_contents($this->checkout.'/'.$configuration))->not->toContain('packages/shipkit-core');
        }

        expect((string) file_get_contents($this->checkout.'/phpstan.neon'))->toContain('app/')
            ->and((string) file_get_contents($this->checkout.'/rector.php'))->toContain("__DIR__.'/app'")
            ->and((string) file_get_contents($this->checkout.'/phpunit.xml'))->toContain('vendor/shipwithian/shipkit-core/tests')
            ->and(simplexml_load_file($this->checkout.'/phpunit.xml'))->not->toBeFalse()
            ->and((new Process(['php', '-l', 'rector.php'], $this->checkout))->run())->toBe(0);
    });
});

it('refuses a version that is not major.minor.patch', function (string $version) {
    commitCheckoutOfProject($this->checkout);

    $release = releaseScript($version, $this->checkout);
    $release->run();

    expect($release->isSuccessful())->toBeFalse()
        ->and($release->getErrorOutput())->toContain('The version must look like 1.0.0')
        ->and(is_dir($this->checkout.'/packages'))->toBeTrue();
})->with(['1.0', 'v1.0.0', '1.0.0-rc1', 'latest']);

it('refuses to release a version that is already tagged', function () {
    commitCheckoutOfProject($this->checkout);
    runInCheckout($this->checkout, ['git', 'tag', 'v2.0.0']);

    $release = releaseScript('2.0.0', $this->checkout);
    $release->run();

    expect($release->isSuccessful())->toBeFalse()
        ->and($release->getErrorOutput())->toContain('The tag v2.0.0 already exists');
});

it('refuses to release from a checkout with uncommitted changes', function () {
    commitCheckoutOfProject($this->checkout);
    file_put_contents($this->checkout.'/composer.json', "\n", FILE_APPEND);

    $release = releaseScript('1.0.0', $this->checkout);
    $release->run();

    expect($release->isSuccessful())->toBeFalse()
        ->and($release->getErrorOutput())->toContain('uncommitted changes');
});

it('refuses to split the package without a token', function () {
    $split = new Process([base_path('.github/scripts/split-core.sh'), '1.0.0'], base_path(), ['SHIPKIT_CORE_SPLIT_TOKEN' => '']);
    $split->run();

    expect($split->isSuccessful())->toBeFalse()
        ->and($split->getErrorOutput())->toContain('SHIPKIT_CORE_SPLIT_TOKEN is not set');
});

it('refuses to tag the split with a version that is not major.minor.patch', function () {
    $split = new Process([base_path('.github/scripts/split-core.sh'), 'v1.0'], base_path(), ['SHIPKIT_CORE_SPLIT_TOKEN' => 'unused']);
    $split->run();

    expect($split->isSuccessful())->toBeFalse()
        ->and($split->getErrorOutput())->toContain('The version must look like 1.0.0');
});

it('refuses a core source the fresh-project script does not know', function (array $environment, string $message) {
    $project = new Process([base_path('.github/scripts/fresh-project.sh'), sys_get_temp_dir().'/never-created-'.Str::uuid()], base_path(), $environment);
    $project->run();

    expect($project->isSuccessful())->toBeFalse()
        ->and($project->getErrorOutput())->toContain($message);
})->with([
    'a misspelled source' => [['SHIPKIT_CORE_SOURCE' => 'Packagist'], "Unknown SHIPKIT_CORE_SOURCE 'Packagist'"],
    'a split with no folder' => [['SHIPKIT_CORE_SOURCE' => 'split', 'SHIPKIT_CORE_VERSION' => '1.0.0'], 'SHIPKIT_CORE_PATH must name the split package'],
]);

/**
 * Copy the project's files, including ones not committed yet, into a fresh repository with one commit.
 */
function commitCheckoutOfProject(string $directory): void
{
    $listing = new Process(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], base_path());
    $listing->mustRun();

    foreach (array_filter(explode("\0", $listing->getOutput())) as $file) {
        if (! is_file(base_path($file))) {
            continue;
        }

        File::ensureDirectoryExists(dirname("{$directory}/{$file}"));
        copy(base_path($file), "{$directory}/{$file}");
        chmod("{$directory}/{$file}", fileperms(base_path($file)) & 0777);
    }

    runInCheckout($directory, ['git', 'init', '--quiet']);
    runInCheckout($directory, ['git', 'add', '--all']);
    runInCheckout($directory, ['git', '-c', 'user.name=Test', '-c', 'user.email=test@shipkit.invalid', 'commit', '--quiet', '-m', 'main']);
}

/**
 * @param  list<string>  $command
 */
function runInCheckout(string $directory, array $command): string
{
    $process = new Process($command, $directory);
    $process->mustRun();

    return trim($process->getOutput());
}

function releaseScript(string $version, string $directory): Process
{
    return new Process(
        [base_path('.github/scripts/prepare-release.sh'), $version, $directory],
        null,
        ['SHIPKIT_RELEASE_SKIP_LOCK' => '1'],
    );
}
