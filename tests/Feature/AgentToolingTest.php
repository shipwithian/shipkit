<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Repository roots whose paths agent documents may reference and that are committed.
 * Lazily created (`docs/`) and generated or ignored (`public/`, `storage/`) paths are not checked.
 *
 * @var list<string>
 */
const CHECKED_ROOTS = ['.ai/', 'app/', 'bootstrap/', 'config/', 'database/factories/', 'database/migrations/', 'database/seeders/', 'resources/js/', 'routes/', 'stubs/', 'tests/', '.github/', 'vendor/shipwithian/'];

/**
 * Where shipkit-core keeps the agent guidance every ShipKit project shares.
 */
const CORE_GUIDANCE = 'vendor/shipwithian/shipkit-core/resources';

it('gives every skill frontmatter whose name matches its folder', function () {
    $problems = [];

    foreach (skillFolders() as $skillPath) {
        $folder = basename($skillPath);
        $frontmatter = skillFrontmatter("{$skillPath}/SKILL.md");

        if ($frontmatter === null) {
            $problems[] = "{$folder}: SKILL.md is missing or has no YAML frontmatter";

            continue;
        }

        if (($frontmatter['name'] ?? null) !== $folder) {
            $problems[] = "{$folder}: frontmatter name is [".($frontmatter['name'] ?? '').'], expected the folder name';
        }

        if (blank($frontmatter['description'] ?? null)) {
            $problems[] = "{$folder}: frontmatter has no description, so no agent can discover it";
        }
    }

    expect($problems)->toBeEmpty(implode(PHP_EOL, $problems));
});

it('ships the upstream license with every vendored skill', function () {
    $missing = [];

    foreach (array_map(fn (string $folder): string => "{$folder}/SKILL.md", skillFolders()) as $skillFile) {
        $frontmatter = skillFrontmatter($skillFile) ?? [];

        if (isset($frontmatter['metadata']['source']) && ! is_file(dirname($skillFile).'/LICENSE')) {
            $missing[] = basename(dirname($skillFile)).' is vendored from '.$frontmatter['metadata']['source'].' but has no LICENSE';
        }
    }

    expect($missing)->toBeEmpty(implode(PHP_EOL, $missing));
});

it('only references repository paths that exist', function () {
    $broken = [];

    foreach (agentDocuments() as $document) {
        preg_match_all('/`([^`\s]+)`/', (string) file_get_contents($document), $matches);

        foreach (array_unique($matches[1]) as $reference) {
            $path = rtrim($reference, '.,:;');

            if (! Str::startsWith($path, CHECKED_ROOTS) || Str::contains($path, ['{', '<', '*', '$', '...'])) {
                continue;
            }

            if (! file_exists(base_path($path))) {
                $broken[] = Str::after($document, base_path().'/').": `{$path}`";
            }
        }
    }

    expect($broken)->toBeEmpty(implode(PHP_EOL, $broken));
});

it('only references markdown links that resolve', function () {
    $broken = [];

    foreach (agentDocuments() as $document) {
        preg_match_all('/\]\(([^)#\s]+)(?:#[^)]*)?\)/', (string) file_get_contents($document), $matches);

        foreach (array_unique($matches[1]) as $link) {
            if (Str::startsWith($link, ['http://', 'https://', 'mailto:'])) {
                continue;
            }

            if (! file_exists(dirname($document).'/'.$link)) {
                $broken[] = Str::after($document, base_path().'/').": ({$link})";
            }
        }
    }

    expect($broken)->toBeEmpty(implode(PHP_EOL, $broken));
});

it('only references artisan commands and composer scripts that exist', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $commands = array_keys(Artisan::all());
    $scripts = array_keys($composer['scripts'] ?? []);

    /** Dev-only packages register their commands outside the testing environment. */
    $devCommandPrefixes = isset($composer['require-dev']['laravel/boost']) ? ['boost:'] : [];
    $unknown = [];

    foreach (agentDocuments() as $document) {
        $source = (string) file_get_contents($document);
        $relative = Str::after($document, base_path().'/');

        preg_match_all('/php artisan ([a-z][\w:-]*)/', $source, $artisan);

        foreach (array_unique($artisan[1]) as $command) {
            if (! in_array($command, $commands, true) && ! Str::startsWith($command, $devCommandPrefixes)) {
                $unknown[] = "{$relative}: php artisan {$command}";
            }
        }

        preg_match_all('/composer (?:run )?([a-z][\w:-]*)/', $source, $composerCalls);

        foreach (array_unique($composerCalls[1]) as $script) {
            if (! in_array($script, [...$scripts, 'install', 'require', 'update', 'show', 'dump-autoload'], true)) {
                $unknown[] = "{$relative}: composer {$script}";
            }
        }
    }

    expect($unknown)->toBeEmpty(implode(PHP_EOL, $unknown));
});

it('maps every rule file in the rules index', function () {
    $index = (string) file_get_contents(base_path('.ai/rules/index.md'));
    $unmapped = [];

    foreach ([...glob(base_path('.ai/rules/*.md')), ...glob(base_path('.ai/rules/contexts/*.md')), ...glob(base_path(CORE_GUIDANCE.'/rules/*.md'))] as $ruleFile) {
        $relative = Str::after($ruleFile, base_path().'/');

        if ($relative !== '.ai/rules/index.md' && ! str_contains($index, $relative)) {
            $unmapped[] = $relative;
        }
    }

    expect($unmapped)->toBeEmpty(implode(PHP_EOL, $unmapped));
});

it('keeps the committed CLAUDE.md and AGENTS.md in step with .ai/guidelines', function () {
    $stale = [];

    foreach (['CLAUDE.md', 'AGENTS.md'] as $generated) {
        $compiled = is_file(base_path($generated)) ? (string) file_get_contents(base_path($generated)) : '';

        foreach ([...glob(base_path('.ai/guidelines/*.md')), ...glob(base_path(CORE_GUIDANCE.'/boost/guidelines/*.md'))] as $guideline) {
            if (! str_contains($compiled, trim((string) file_get_contents($guideline)))) {
                $stale[] = "{$generated} does not contain the current ".Str::after($guideline, base_path().'/').'; run `php artisan boost:update`';
            }
        }
    }

    expect($stale)->toBeEmpty(implode(PHP_EOL, $stale));
});

it('takes the shared guidance from shipkit-core instead of copying it', function () {
    $coreRules = array_map(basename(...), glob(base_path(CORE_GUIDANCE.'/rules/*.md')));
    $coreSkills = array_map(basename(...), glob(base_path(CORE_GUIDANCE.'/boost/skills/*'), GLOB_ONLYDIR));
    $coreGuidelines = array_map(basename(...), glob(base_path(CORE_GUIDANCE.'/boost/guidelines/*.md')));
    $boost = json_decode((string) file_get_contents(base_path('boost.json')), true);

    expect($coreRules)->not->toBeEmpty('shipkit-core ships no rule files')
        ->and($coreSkills)->not->toBeEmpty('shipkit-core ships no skills')
        ->and($coreGuidelines)->not->toBeEmpty('shipkit-core ships no guidelines')
        ->and(in_array('shipwithian/shipkit-core', $boost['packages'] ?? [], true))->toBeTrue('boost.json must list shipwithian/shipkit-core under packages, or boost:update ignores its guidelines and skills')
        ->and(array_intersect($coreRules, array_map(basename(...), glob(base_path('.ai/rules/*.md')))))->toBeEmpty('These rule files are copies of shipkit-core\'s; delete the project copy')
        ->and(array_intersect($coreSkills, array_map(basename(...), glob(base_path('.ai/skills/*'), GLOB_ONLYDIR))))->toBeEmpty('These skills are copies of shipkit-core\'s; delete the project copy')
        ->and(array_intersect($coreGuidelines, array_map(basename(...), glob(base_path('.ai/guidelines/*.md')))))->toBeEmpty('These guidelines are copies of shipkit-core\'s; delete the project copy');
});

/**
 * Every skill folder: the project's own and the ones shipkit-core ships.
 *
 * @return list<string>
 */
function skillFolders(): array
{
    return [...glob(base_path('.ai/skills/*'), GLOB_ONLYDIR), ...glob(base_path(CORE_GUIDANCE.'/boost/skills/*'), GLOB_ONLYDIR)];
}

/**
 * @return array<string, mixed>|null
 */
function skillFrontmatter(string $skillFile): ?array
{
    if (! is_file($skillFile) || ! preg_match('/^---\R(.*?)\R---\R/s', (string) file_get_contents($skillFile), $match)) {
        return null;
    }

    $frontmatter = Yaml::parse($match[1]);

    return is_array($frontmatter) ? $frontmatter : null;
}

/**
 * Every agent document: the project's guidelines, rules, issue tracker reference, and custom skills,
 * plus the guidance shipkit-core ships.
 *
 * @return list<string>
 */
function agentDocuments(): array
{
    $files = new AppendIterator;

    foreach (['.ai', CORE_GUIDANCE] as $root) {
        if (is_dir(base_path($root))) {
            $files->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS)));
        }
    }

    return collect($files)
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->filter(fn (string $path): bool => str_ends_with($path, '.md'))
        ->values()
        ->all();
}
