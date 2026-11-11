<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ShipKit\Core\Architecture\ProjectLayout;

$layout = ProjectLayout::current();
$app = $layout->appNamespace();

/**
 * Features that deliberately report failures with Laravel's own exceptions, with the reason,
 * from `shipkit.architecture.features_using_framework_exceptions`. Every other feature must raise
 * named exceptions that extend DomainException.
 *
 * @var array<string, string> $usesFrameworkExceptions
 */
$usesFrameworkExceptions = (array) $layout->config('architecture.features_using_framework_exceptions', []);

/**
 * The shared business folders that exist, by namespace: models, policies, and exceptions.
 *
 * @var list<string> $sharedNamespaces
 */
$sharedNamespaces = array_values(array_map(
    fn (string $folder): string => $layout->appNamespaceFor($folder),
    array_filter(['Models', 'Policies', 'Exceptions'], fn (string $folder): bool => is_dir($layout->appPath($folder))),
));

// Each arch() expects one namespace: Pest passes `->not->toUse()` on an array of namespaces without checking them.
foreach ([...$layout->featureNamespaces('Actions'), ...$layout->featureNamespaces('Queries'), ...$sharedNamespaces] as $namespace) {
    arch("{$namespace} does not depend on the HTTP layer")
        ->expect($namespace)
        ->not->toUse(["{$app}Http", 'Illuminate\Http', 'Illuminate\Foundation\Http\FormRequest']);
}

arch('controllers do not use the database directly')
    ->expect("{$app}Http\\Controllers")
    ->not->toUse('Illuminate\Support\Facades\DB');

foreach ($layout->featureNamespaces('Actions') as $namespace) {
    arch("{$namespace} holds named use cases with a handle method")
        ->expect($namespace)
        ->toBeClasses()
        ->toHaveSuffix('Action')
        ->toHaveMethod('handle');
}

foreach ($layout->featureNamespaces('Queries') as $namespace) {
    arch("{$namespace} holds named reads with a handle method")
        ->expect($namespace)
        ->toBeClasses()
        ->toHaveSuffix('Query')
        ->toHaveMethod('handle');
}

if (is_dir($layout->appPath('Exceptions'))) {
    arch('exceptions extend the base domain exception')
        ->expect("{$app}Exceptions")
        ->toExtend('ShipKit\Core\Exceptions\DomainException');
}

foreach ($layout->features() as $feature) {
    if (array_key_exists($feature, $usesFrameworkExceptions)) {
        continue;
    }

    foreach ([...$layout->featureNamespaces('Actions', $feature), ...$layout->featureNamespaces('Queries', $feature)] as $namespace) {
        arch("{$namespace} reports broken rules as named exceptions")
            ->expect($namespace)
            ->not->toUse('Illuminate\Validation\ValidationException');
    }
}

foreach (array_filter($sharedNamespaces, fn (string $namespace): bool => $namespace !== "{$app}Exceptions") as $namespace) {
    arch("{$namespace} reports broken rules as named exceptions")
        ->expect($namespace)
        ->not->toUse('Illuminate\Validation\ValidationException');
}

arch('form requests validate only, with no authorize() logic')
    ->expect("{$app}Http\\Requests")
    ->not->toHaveMethod('authorize');

it('keeps each kind of class in its standard folder', function () {
    $violations = ProjectLayout::current()->misplacedClasses();

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('follows the MCP tool conventions', function () {
    $violations = ProjectLayout::current()->toolConventionViolations();

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('authorizes policies through permissions, never role names', function () {
    $violations = [];

    foreach (ProjectLayout::current()->phpFiles('app/Policies') as $path) {
        if (preg_match_all('/->(?:hasRole|hasAnyRole|hasAllRoles|hasExactRoles)\(|\bSystemRole\b/', (string) file_get_contents($path), $matches)) {
            foreach ($matches[0] as $match) {
                $violations[] = "{$path}: {$match} (check a permission with \$user->can() instead)";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('lists every exposed field instead of serializing whole models', function () {
    $violations = [];

    foreach (ProjectLayout::current()->phpFiles('app/Http') as $path) {
        if (preg_match_all('/->(?:toArray|attributesToArray|getAttributes)\(\)|parent::toArray\(/', (string) file_get_contents($path), $matches)) {
            foreach ($matches[0] as $match) {
                $violations[] = "{$path}: {$match} (map each field explicitly so new columns are never exposed by default)";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('receives the acting user instead of reading ambient request state', function () {
    $layout = ProjectLayout::current();
    $violations = [];
    $folders = [
        ...array_map(fn (string $feature): string => "app/Actions/{$feature}", $layout->features()),
        ...array_map(fn (string $feature): string => "app/Queries/{$feature}", $layout->features()),
        'app/Models',
        'app/Policies',
        'app/Exceptions',
    ];

    foreach ($folders as $folder) {
        foreach ($layout->phpFiles($folder) as $path) {
            if (preg_match_all('/\bauth\(|\bAuth::(?:user|id|check|guest|hasUser)\b|\brequest\(|\bsession\(/', (string) file_get_contents($path), $matches)) {
                foreach ($matches[0] as $match) {
                    $violations[] = "{$path}: {$match} (take the acting user as a `User \$actor` parameter; the controller passes \$request->user())";
                }
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('never lists secrets in resources or Inertia props', function () {
    $layout = ProjectLayout::current();
    $violations = [];
    $files = [...$layout->phpFiles('app/Http/Resources'), ...$layout->phpFiles('app/Http/Props'), ...array_filter([$layout->appPath('Http/Middleware/HandleInertiaRequests.php')], is_file(...))];

    foreach ($files as $path) {
        $source = (string) file_get_contents($path);

        if (preg_match_all("/['\"](?:token|plain_?text_?token|password|current_password|secret|remember_token|two_factor_\\w+)['\"]\\s*=>|->(?:plainTextToken|two_factor_secret|two_factor_recovery_codes|remember_token)\\b/", $source, $matches)) {
            foreach ($matches[0] as $match) {
                $violations[] = "{$path}: {$match} (a secret is shown once, by the controller that creates it, and never listed by a resource or shared prop)";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('keeps Eloquent queries out of controllers', function () {
    $violations = [];

    foreach (ProjectLayout::current()->phpFiles('app/Http/Controllers') as $path) {
        $source = (string) file_get_contents($path);

        preg_match_all('/^use ([\w\\\\]+);$/m', $source, $imports);

        foreach ($imports[1] as $import) {
            if (! is_subclass_of($import, Model::class)) {
                continue;
            }

            $model = class_basename($import);

            if (preg_match("/\\b{$model}::(?!class\\b)(?![A-Z][A-Z0-9_]*\\b)\\w+/", $source, $match)) {
                $violations[] = "{$path}: {$match[0]} calls the {$model} model statically. A controller reads through a Query object and writes through an Action; constants such as {$model}::MAXIMUM and {$model}::class are fine";
            }
        }

        if (preg_match_all('/->\w+\(\)->(?:where\w*|orderBy\w*|latest|oldest|paginate)\(/', $source, $matches)) {
            foreach ($matches[0] as $match) {
                $violations[] = "{$path}: {$match} builds a relationship query in a controller; move it into a Query object";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('documents every action, query, and exception in a feature rule file', function () {
    $layout = ProjectLayout::current();
    $missing = [];
    $allRules = '';

    foreach ($layout->features() as $feature) {
        $ruleFile = $layout->path('.ai/rules/features/'.Str::kebab($feature).'.md');
        $rules = is_file($ruleFile) ? (string) file_get_contents($ruleFile) : '';
        $allRules .= $rules;

        foreach (['Actions', 'Queries'] as $folder) {
            foreach (glob($layout->appPath("{$folder}/{$feature}/*.php")) ?: [] as $classPath) {
                $class = basename($classPath, '.php');

                if (! str_contains($rules, $class)) {
                    $missing[] = '.ai/rules/features/'.Str::kebab($feature).".md does not mention {$folder}\\{$feature}\\{$class}";
                }
            }
        }
    }

    foreach (glob($layout->appPath('Exceptions/*.php')) ?: [] as $classPath) {
        $class = basename($classPath, '.php');

        if (! str_contains($allRules, $class)) {
            $missing[] = "No feature rule file in .ai/rules/features mentions Exceptions\\{$class}";
        }
    }

    expect($missing)->toBeEmpty(implode(PHP_EOL, $missing));
});

it('guards invariants only through GuardsInvariants', function () {
    $violations = [];

    foreach (ProjectLayout::current()->phpFiles('app/Models') as $path) {
        $source = (string) file_get_contents($path);
        $isPivot = (bool) preg_match('/\bextends\s+(?:Morph)?Pivot\b/', $source);
        $usesTrait = (bool) preg_match('/\buse\s+(?:[^;{]*,\s*)?GuardsInvariants\b/', $source);
        $usesPivotTrait = str_contains($source, 'GuardsPivotInvariants');

        if (preg_match('/static::(?:updating|deleting|saving|creating)\(/', $source)) {
            $violations[] = "{$path}: registers a model event guard directly; call ensure...() from guardCreate()/guardUpdate()/guardDelete() with GuardsInvariants instead";
        }

        if (preg_match('/function guard(?:Create|Update|Delete)\(/', $source) && ! $usesTrait && ! $usesPivotTrait) {
            $violations[] = "{$path}: defines guardCreate()/guardUpdate()/guardDelete() without `use GuardsInvariants;`, so the guards never run";
        }

        if ($isPivot && $usesTrait && ! $usesPivotTrait) {
            $violations[] = "{$path}: a pivot must use GuardsPivotInvariants, not GuardsInvariants, or its detach guard is skipped when model events are silenced";
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('names HTTP and feature test folders after a feature', function () {
    $layout = ProjectLayout::current();
    $features = $layout->features();

    /** @var array<string, array<string, string>> $nonFeatureFolders */
    $nonFeatureFolders = (array) $layout->config('architecture.non_feature_folders', []);
    $strays = [];

    foreach (['app/Http/Controllers', 'app/Http/Requests', 'app/Http/Props', 'tests/Feature'] as $directory) {
        $allowed = [...$features, ...array_keys($nonFeatureFolders[$directory] ?? [])];

        foreach (glob($layout->path($directory.'/*'), GLOB_ONLYDIR) ?: [] as $folder) {
            if (! in_array(basename($folder), $allowed, true)) {
                $strays[] = "{$directory}/".basename($folder).' matches no feature. Rename it after a feature in app/Actions or app/Queries, or list it in shipkit.architecture.non_feature_folders with the reason';
            }
        }
    }

    expect($strays)->toBeEmpty(implode(PHP_EOL, $strays));
});
