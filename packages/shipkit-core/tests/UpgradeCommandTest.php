<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

uses(TestCase::class);

/**
 * The template's files at each release. A file missing from a version does not exist in it.
 *
 * @var array<string, array<string, string>>
 */
const TEMPLATE_RELEASES = [
    '1.0.0' => [
        '.gitattributes' => "/TEMPLATE.md export-ignore\n",
        'TEMPLATE.md' => "The template's own notes.\n",
        'app/Untouched.php' => "<?php\n// one\n",
        'app/Edited.php' => "<?php\n// top\n// middle\n// bottom\n",
        'app/Clashing.php' => "<?php\n// shared line\n",
        'app/DeletedHere.php' => "<?php\n// deleted by the project\n",
        'app/Retired.php' => "<?php\n// retired by the template\n",
        'app/RetiredButEdited.php' => "<?php\n// retired by the template\n",
        'app/AlreadyThere.php' => "<?php\n// old\n",
        'composer.lock' => "{\"v\": 1}\n",
        'app/Ignored.php' => "<?php\n// one\n",
        'public/logo.png' => "\x89PNG\0before",
    ],
    '1.1.0' => [
        '.gitattributes' => "/TEMPLATE.md export-ignore\n",
        'TEMPLATE.md' => "The template's own notes, changed.\n",
        'app/Untouched.php' => "<?php\n// two\n",
        'app/Edited.php' => "<?php\n// top\n// middle\n// bottom, improved\n",
        'app/Clashing.php' => "<?php\n// template's line\n",
        'app/DeletedHere.php' => "<?php\n// changed by the template\n",
        'app/AlreadyThere.php' => "<?php\n// new\n",
        'app/Brand.php' => "<?php\n// added by the template\n",
        'composer.lock' => "{\"v\": 2}\n",
        'app/Ignored.php' => "<?php\n// two\n",
        'public/logo.png' => "\x89PNG\0after",
        'bin/run.sh' => "#!/bin/sh\necho run\n",
    ],
    '1.2.0' => [
        '.gitattributes' => "/TEMPLATE.md export-ignore\n",
        'TEMPLATE.md' => "The template's own notes, changed.\n",
        'app/Untouched.php' => "<?php\n// three\n",
        'app/Edited.php' => "<?php\n// top\n// middle\n// bottom, improved\n",
        'app/Clashing.php' => "<?php\n// template's line\n",
        'app/DeletedHere.php' => "<?php\n// changed by the template\n",
        'app/AlreadyThere.php' => "<?php\n// new\n",
        'app/Brand.php' => "<?php\n// added by the template\n",
        'composer.lock' => "{\"v\": 2}\n",
        'app/Ignored.php' => "<?php\n// two\n",
        'public/logo.png' => "\x89PNG\0after",
        'bin/run.sh' => "#!/bin/sh\necho run\n",
    ],
];

/**
 * @param  list<string>  $command
 */
function git(string $directory, array $command): string
{
    $process = new Process(['git', '-c', 'user.name=Test', '-c', 'user.email=test@shipkit.invalid', ...$command], $directory);
    $process->mustRun();

    return trim($process->getOutput());
}

/**
 * @param  array<string, string>  $files
 */
function replaceFiles(string $directory, array $files): void
{
    foreach (File::allFiles($directory) as $file) {
        if (! str_starts_with($file->getRelativePathname(), '.git/') && ! isset($files[$file->getRelativePathname()])) {
            File::delete($file->getPathname());
        }
    }

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$directory}/{$path}"));
        File::put("{$directory}/{$path}", $contents);

        if (str_ends_with($path, '.sh')) {
            chmod("{$directory}/{$path}", 0755);
        }
    }
}

beforeEach(function () {
    $root = sys_get_temp_dir().'/shipkit-upgrade-'.bin2hex(random_bytes(6));
    $this->template = "{$root}/template";
    $this->project = "{$root}/project";
    $this->root = $root;

    File::ensureDirectoryExists($this->template);
    git($this->template, ['init', '--quiet', '-b', 'main']);

    foreach (TEMPLATE_RELEASES as $version => $files) {
        replaceFiles($this->template, $files);
        git($this->template, ['add', '--all']);
        git($this->template, ['commit', '--quiet', '-m', "release {$version}"]);
        git($this->template, ['tag', "v{$version}"]);
    }

    File::ensureDirectoryExists($this->project);
    git($this->project, ['init', '--quiet', '-b', 'main']);
    replaceFiles($this->project, TEMPLATE_RELEASES['1.0.0']);
    File::delete("{$this->project}/TEMPLATE.md");
    File::put("{$this->project}/shipkit.json", '{"template": "1.0.0", "exclude": ["app/Ignored.php"]}');
    File::put("{$this->project}/app/Edited.php", "<?php\n// top, ours\n// middle\n// bottom\n");
    File::put("{$this->project}/app/Clashing.php", "<?php\n// our line\n");
    File::delete("{$this->project}/app/DeletedHere.php");
    File::put("{$this->project}/app/RetiredButEdited.php", "<?php\n// retired by the template, but we use it\n");
    File::put("{$this->project}/app/AlreadyThere.php", "<?php\n// new\n");
    File::put("{$this->project}/public/logo.png", "\x89PNG\0ours");
    File::ensureDirectoryExists("{$this->project}/vendor/shipwithian/shipkit-core");
    File::put("{$this->project}/vendor/shipwithian/shipkit-core/UPGRADE.md", "# Upgrade notes\n\n## Upgrading to 1.2\n\nRun the 1.2 step.\n\n## Upgrading to 1.1\n\nRun the 1.1 step.\n\n## Upgrading to 1.0\n\nAn older step.\n");
    File::put("{$this->project}/.gitignore", "/vendor\n");
    git($this->project, ['add', '--all']);
    git($this->project, ['commit', '--quiet', '-m', 'our project']);

    app()->setBasePath($this->project);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

/**
 * The outcome the report gives each file, from its "path ... outcome" lines.
 *
 * @return array<string, string>
 */
function outcomesIn(string $output): array
{
    preg_match_all('/^\s*(\S+\.\w+)\s+\.{2,}\s+(.+?)\s*$/m', $output, $matches, PREG_SET_ORDER);

    return collect($matches)->mapWithKeys(fn (array $match): array => [$match[1] => $match[2]])->all();
}

it('reports what each changed template file would do, and changes nothing', function () {
    $before = git($this->project, ['status', '--porcelain', '--ignored']);

    $this->withoutMockingConsoleOutput();
    $exitCode = Artisan::call('shipkit:upgrade', ['--to' => '1.1.0', '--source' => $this->template]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and(outcomesIn($output))->toBe([
            'app/AlreadyThere.php' => 'already applied',
            'app/Brand.php' => 'new',
            'app/Clashing.php' => 'conflicts',
            'app/DeletedHere.php' => 'deleted in your project',
            'app/Edited.php' => 'merges cleanly',
            'app/Retired.php' => 'removed',
            'app/RetiredButEdited.php' => 'conflicts',
            'app/Untouched.php' => 'updates',
            'bin/run.sh' => 'new',
            'public/logo.png' => 'conflicts',
        ])
        ->and($output)->toContain('Run the 1.1 step.')
        ->not->toContain('Run the 1.2 step.')
        ->not->toContain('An older step.')
        ->and(git($this->project, ['status', '--porcelain', '--ignored']))->toBe($before)
        ->and(git($this->project, ['for-each-ref', 'refs/shipkit']))->toBe('')
        ->and(git($this->project, ['remote']))->toBe('');
});

it('covers every version in the range, with each one\'s upgrade notes', function () {
    $this->withoutMockingConsoleOutput();
    Artisan::call('shipkit:upgrade', ['--to' => '1.2.0', '--source' => $this->template]);
    $output = Artisan::output();

    expect(outcomesIn($output)['app/Untouched.php'])->toBe('updates')
        ->and($output)->toContain('Run the 1.1 step.')
        ->and($output)->toContain('Run the 1.2 step.');
});

it('starts from --from when the project recorded no version', function () {
    File::put("{$this->project}/shipkit.json", '{"exclude": ["app/Ignored.php"]}');
    git($this->project, ['commit', '--quiet', '-am', 'no version']);

    $this->withoutMockingConsoleOutput();

    expect(Artisan::call('shipkit:upgrade', ['--to' => '1.1.0', '--source' => $this->template]))->toBe(1)
        ->and(Artisan::output())->toContain('--from')
        ->and(Artisan::call('shipkit:upgrade', ['--from' => '1.0.0', '--to' => '1.1.0', '--source' => $this->template]))->toBe(0);
});

it('refuses to run before anything changes', function (Closure $arrange, array $options, string $message) {
    $arrange($this);
    $this->withoutMockingConsoleOutput();

    expect(Artisan::call('shipkit:upgrade', ['--source' => $this->template, ...$options]))->toBe(1)
        ->and(Artisan::output())->toContain($message)
        ->and(git($this->project, ['for-each-ref', 'refs/shipkit']))->toBe('');
})->with([
    'uncommitted changes' => [fn ($test) => File::put("{$test->project}/app/Untouched.php", "<?php\n// wip\n"), ['--to' => '1.1.0'], 'uncommitted changes'],
    'an unknown version' => [fn () => null, ['--to' => '9.9.9'], 'v9.9.9'],
    'a target that is not newer' => [fn () => null, ['--to' => '1.0.0'], 'newer than'],
    'a version that is not major.minor.patch' => [fn () => null, ['--to' => 'latest'], 'major.minor.patch'],
    'an unreachable source' => [fn ($test) => File::deleteDirectory($test->template), ['--to' => '1.1.0'], 'Could not fetch'],
]);

it('refuses a folder that is not a Git repository', function () {
    File::deleteDirectory("{$this->project}/.git");
    $this->withoutMockingConsoleOutput();

    expect(Artisan::call('shipkit:upgrade', ['--to' => '1.1.0', '--source' => $this->template]))->toBe(1)
        ->and(Artisan::output())->toContain('Git repository');
});

/**
 * @param  array<string, string>  $options
 */
function applyUpgrade(array $options): array
{
    test()->withoutMockingConsoleOutput();
    $exitCode = Artisan::call('shipkit:upgrade', ['--apply' => true, ...$options]);

    return [$exitCode, Artisan::output()];
}

it('applies the upgrade as a three-way merge, and stamps the new version', function () {
    [$exitCode, $output] = applyUpgrade(['--to' => '1.1.0', '--source' => $this->template]);
    $file = fn (string $path): ?string => File::exists("{$this->project}/{$path}") ? File::get("{$this->project}/{$path}") : null;

    expect($exitCode)->toBe(0)
        ->and($file('app/Untouched.php'))->toBe("<?php\n// two\n")
        ->and($file('app/Edited.php'))->toBe("<?php\n// top, ours\n// middle\n// bottom, improved\n")
        ->and($file('app/Clashing.php'))->toContain("<<<<<<< yours\n// our line\n=======\n// template's line\n>>>>>>> template after\n")
        ->and($file('app/Brand.php'))->toBe("<?php\n// added by the template\n")
        ->and($file('app/Retired.php'))->toBeNull()
        ->and($file('app/RetiredButEdited.php'))->toBe("<?php\n// retired by the template, but we use it\n")
        ->and($file('app/DeletedHere.php'))->toBeNull()
        ->and($file('app/AlreadyThere.php'))->toBe("<?php\n// new\n")
        ->and($file('public/logo.png'))->toBe("\x89PNG\0ours")
        ->and($file('app/Ignored.php'))->toBe("<?php\n// one\n")
        ->and($file('composer.lock'))->toBe("{\"v\": 1}\n")
        ->and($file('TEMPLATE.md'))->toBeNull()
        ->and(is_executable("{$this->project}/bin/run.sh"))->toBeTrue()
        ->and(json_decode((string) $file('shipkit.json'), true))->toBe(['template' => '1.1.0', 'exclude' => ['app/Ignored.php']])
        ->and($output)->toContain('app/Clashing.php')
        ->and($output)->toContain('composer update')
        ->and(git($this->project, ['diff', '--cached', '--name-only']))->toBe('')
        ->and(git($this->project, ['for-each-ref', 'refs/shipkit']))->toBe('')
        ->and(git($this->project, ['log', '--oneline']))->not->toContain('1.1.0');
});

it('applies a range of several versions in one run', function () {
    [$exitCode] = applyUpgrade(['--to' => '1.2.0', '--source' => $this->template]);

    expect($exitCode)->toBe(0)
        ->and(File::get("{$this->project}/app/Untouched.php"))->toBe("<?php\n// three\n")
        ->and(json_decode(File::get("{$this->project}/shipkit.json"), true)['template'])->toBe('1.2.0');
});

it('does what the report said it would', function () {
    test()->withoutMockingConsoleOutput();
    Artisan::call('shipkit:upgrade', ['--to' => '1.1.0', '--source' => $this->template]);
    $predicted = outcomesIn(Artisan::output());

    [, $output] = applyUpgrade(['--to' => '1.1.0', '--source' => $this->template]);

    expect(outcomesIn($output))->toBe($predicted);
});

it('stamps nothing and changes nothing when it cannot run', function () {
    File::put("{$this->project}/app/Untouched.php", "<?php\n// wip\n");

    [$exitCode] = applyUpgrade(['--to' => '1.1.0', '--source' => $this->template]);

    expect($exitCode)->toBe(1)
        ->and(json_decode(File::get("{$this->project}/shipkit.json"), true)['template'])->toBe('1.0.0')
        ->and(File::exists("{$this->project}/app/Brand.php"))->toBeFalse();
});
