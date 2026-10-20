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

it('turns main into a release commit that requires the published shipkit-core', function () {
    commitCheckoutOfProject($this->checkout);
    $main = runInCheckout($this->checkout, ['git', 'rev-parse', 'HEAD']);

    $release = releaseScript('1.4.2', $this->checkout);
    $release->mustRun();

    $composer = json_decode((string) file_get_contents($this->checkout.'/composer.json'), true);

    expect($composer['require']['shipwithian/shipkit-core'])->toBe('^1.4')
        ->and($composer)->not->toHaveKey('repositories')
        ->and(is_dir($this->checkout.'/packages'))->toBeFalse()
        ->and(runInCheckout($this->checkout, ['git', 'rev-parse', 'v1.4.2^{commit}']))->toBe(runInCheckout($this->checkout, ['git', 'rev-parse', 'HEAD']))
        ->and(runInCheckout($this->checkout, ['git', 'rev-parse', 'HEAD~1']))->toBe($main)
        ->and(runInCheckout($this->checkout, ['git', 'status', '--porcelain']))->toBe('');

    foreach (['phpstan.neon', 'rector.php', 'phpunit.xml'] as $configuration) {
        expect((string) file_get_contents($this->checkout.'/'.$configuration))->not->toContain('packages/shipkit-core');
    }

    expect((string) file_get_contents($this->checkout.'/phpstan.neon'))->toContain('app/')
        ->and((string) file_get_contents($this->checkout.'/rector.php'))->toContain("__DIR__.'/app'")
        ->and(simplexml_load_file($this->checkout.'/phpunit.xml'))->not->toBeFalse()
        ->and((new Process(['php', '-l', 'rector.php'], $this->checkout))->run())->toBe(0);
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
