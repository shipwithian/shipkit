<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

arch('the domain does not depend on the HTTP layer')
    ->expect('App\Domain')
    ->not->toUse(['App\Http', 'Illuminate\Http', 'Illuminate\Foundation\Http\FormRequest']);

arch('controllers do not use the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

/**
 * Contexts that deliberately report failures with Laravel's own exceptions, with the reason.
 * Every other context must raise named domain exceptions.
 *
 * @var array<string, string>
 */
$usesFrameworkExceptions = [
    'Authentication' => 'mirrors Fortify, which reports with validation and authorization exceptions',
];

arch('actions are named use cases with a handle method')
    ->expect(contextNamespaces('Actions'))
    ->toBeClasses()
    ->toHaveSuffix('Action')
    ->toHaveMethod('handle');

arch('queries are named reads with a handle method')
    ->expect(contextNamespaces('Queries'))
    ->toBeClasses()
    ->toHaveSuffix('Query')
    ->toHaveMethod('handle');

arch('domain exceptions extend the base domain exception')
    ->expect(contextNamespaces('Exceptions'))
    ->toExtend('App\Domain\Shared\Exceptions\DomainException');

arch('the shared kernel does not depend on any context')
    ->expect('App\Domain\Shared')
    ->not->toUse([...array_map(fn (string $context): string => "App\\Domain\\{$context}", domainContexts()), 'App\Http']);

foreach (domainContexts() as $context) {
    $internals = contextNamespaces(['Models', 'Enums', 'Policies', 'Services'], $context);

    if ($internals !== []) {
        arch("{$context} internals are only used by its own context, the shared identity model, factories, seeders, and the delivery layer")
            ->expect($internals)
            ->toOnlyBeUsedIn(["App\\Domain\\{$context}", 'App\Models', 'App\Http', 'Database\Factories', 'Database\Seeders']);
    }

    arch("{$context} public surface (Actions, Queries, Exceptions) is only used by the domain, the shared identity model, factories, seeders, and the delivery layer")
        ->expect("App\\Domain\\{$context}")
        ->toOnlyBeUsedIn(['App\Domain', 'App\Models', 'App\Http', 'Database\Factories', 'Database\Seeders']);

    if (! array_key_exists($context, $usesFrameworkExceptions)) {
        arch("{$context} reports broken rules as domain exceptions")
            ->expect("App\\Domain\\{$context}")
            ->not->toUse('Illuminate\Validation\ValidationException');
    }
}

arch('form requests validate only, with no authorize() logic')
    ->expect('App\Http\Requests')
    ->not->toHaveMethod('authorize');

it('authorizes policies through permissions, never role names', function () {
    $violations = [];

    foreach (contextNamespaces('Policies') as $namespace) {
        foreach (phpFilesIn(str_replace(['App\\', '\\'], ['app/', '/'], $namespace)) as $path) {
            if (preg_match_all('/->(?:hasRole|hasAnyRole|hasAllRoles|hasExactRoles)\(|\bSystemRole\b/', (string) file_get_contents($path), $matches)) {
                foreach ($matches[0] as $match) {
                    $violations[] = "{$path}: {$match} (check a permission with \$user->can() instead)";
                }
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('lists every exposed field instead of serializing whole models', function () {
    $violations = [];

    foreach (phpFilesIn('app/Http') as $path) {
        if (preg_match_all('/->(?:toArray|attributesToArray|getAttributes)\(\)|parent::toArray\(/', (string) file_get_contents($path), $matches)) {
            foreach ($matches[0] as $match) {
                $violations[] = "{$path}: {$match} (map each field explicitly so new columns are never exposed by default)";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('keeps Eloquent queries out of controllers', function () {
    $violations = [];

    foreach (phpFilesIn('app/Http/Controllers') as $path) {
        $source = (string) file_get_contents($path);

        preg_match_all('/^use ([\w\\\\]+);$/m', $source, $imports);

        foreach ($imports[1] as $import) {
            if (! is_subclass_of($import, Model::class)) {
                continue;
            }

            $model = class_basename($import);

            if (preg_match("/\\b{$model}::(?!class\\b)\\w+/", $source, $match)) {
                $violations[] = "{$path}: {$match[0]}";
            }
        }

        if (preg_match_all('/->\w+\(\)->(?:where\w*|orderBy\w*|latest|oldest|paginate)\(/', $source, $matches)) {
            foreach ($matches[0] as $match) {
                $violations[] = "{$path}: {$match}";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('documents every action, query, and domain exception in its context rule file', function () {
    $missing = [];

    foreach (domainContexts() as $context) {
        $contextPath = repositoryPath("app/Domain/{$context}");
        $ruleFile = repositoryPath('.ai/rules/contexts/'.Str::kebab($context).'.md');
        $rules = is_file($ruleFile) ? (string) file_get_contents($ruleFile) : '';

        foreach (['Actions', 'Queries', 'Exceptions'] as $folder) {
            foreach (glob("{$contextPath}/{$folder}/*.php") as $classPath) {
                $class = basename($classPath, '.php');

                if (! str_contains($rules, $class)) {
                    $missing[] = "{$context}\\{$folder}\\{$class}";
                }
            }
        }
    }

    expect($missing)->toBeEmpty(implode(PHP_EOL, $missing));
});

it('guards invariants only through GuardsInvariants', function () {
    $violations = [];

    foreach ([...phpFilesIn('app/Domain'), ...phpFilesIn('app/Models')] as $path) {
        if (str_ends_with($path, 'Shared/Eloquent/GuardsInvariants.php')) {
            continue;
        }

        $source = (string) file_get_contents($path);

        if (preg_match('/static::(?:updating|deleting|saving|creating)\(/', $source)) {
            $violations[] = "{$path}: registers a model event guard directly; call ensure...() from guardUpdate()/guardDelete() with GuardsInvariants instead";
        }

        if (preg_match('/function guard(?:Update|Delete)\(/', $source) && ! str_contains($source, 'use GuardsInvariants')) {
            $violations[] = "{$path}: defines guardUpdate()/guardDelete() without `use GuardsInvariants;`, so the guards never run";
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('names HTTP and feature test folders after a bounded context', function () {
    $contexts = domainContexts();

    $allowed = [
        'app/Http/Controllers' => [...$contexts, 'Api'],
        'app/Http/Requests' => [...$contexts, 'Api'],
        'tests/Feature' => [...$contexts, 'Auth'],
    ];

    $strays = [];

    foreach ($allowed as $directory => $names) {
        foreach (glob(repositoryPath($directory.'/*'), GLOB_ONLYDIR) as $folder) {
            if (! in_array(basename($folder), $names, true)) {
                $strays[] = $directory.'/'.basename($folder);
            }
        }
    }

    expect($strays)->toBeEmpty(implode(PHP_EOL, $strays));
});

/**
 * @return list<string>
 */
function phpFilesIn(string $directory): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(repositoryPath($directory)));

    return collect($files)
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function domainContexts(): array
{
    return collect(glob(repositoryPath('app/Domain/*'), GLOB_ONLYDIR))
        ->map(fn (string $path): string => basename($path))
        ->reject(fn (string $name): bool => $name === 'Shared')
        ->values()
        ->all();
}

/**
 * Namespaces of the given folders that exist, across every context or within one.
 *
 * @param  list<string>|string  $folders
 * @return list<string>
 */
function contextNamespaces(array|string $folders, ?string $onlyContext = null): array
{
    return collect($onlyContext === null ? domainContexts() : [$onlyContext])
        ->crossJoin((array) $folders)
        ->filter(fn (array $pair): bool => is_dir(repositoryPath("app/Domain/{$pair[0]}/{$pair[1]}")))
        ->map(fn (array $pair): string => "App\\Domain\\{$pair[0]}\\{$pair[1]}")
        ->values()
        ->all();
}

function repositoryPath(string $path = ''): string
{
    return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
}
