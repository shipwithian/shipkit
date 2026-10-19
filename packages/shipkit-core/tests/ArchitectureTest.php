<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ShipKit\Core\Architecture\ProjectLayout;

$layout = ProjectLayout::current();
$app = $layout->appNamespace();

/**
 * Contexts that deliberately report failures with Laravel's own exceptions, with the reason,
 * from `shipkit.architecture.contexts_using_framework_exceptions`. Every other context must raise
 * named domain exceptions.
 *
 * @var array<string, string> $usesFrameworkExceptions
 */
$usesFrameworkExceptions = (array) $layout->config('architecture.contexts_using_framework_exceptions', []);

arch('the domain does not depend on the HTTP layer')
    ->expect("{$app}Domain")
    ->not->toUse(["{$app}Http", 'Illuminate\Http', 'Illuminate\Foundation\Http\FormRequest']);

arch('controllers do not use the database directly')
    ->expect("{$app}Http\\Controllers")
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('actions are named use cases with a handle method')
    ->expect($layout->contextNamespaces('Actions'))
    ->toBeClasses()
    ->toHaveSuffix('Action')
    ->toHaveMethod('handle');

arch('queries are named reads with a handle method')
    ->expect($layout->contextNamespaces('Queries'))
    ->toBeClasses()
    ->toHaveSuffix('Query')
    ->toHaveMethod('handle');

arch('domain exceptions extend the base domain exception')
    ->expect($layout->contextNamespaces('Exceptions'))
    ->toExtend('ShipKit\Core\Exceptions\DomainException');

foreach ($layout->contexts() as $context) {
    $internals = $layout->contextNamespaces(['Models', 'Enums', 'Policies', 'Services'], $context);

    if ($internals !== []) {
        arch("{$context} internals are only used by its own context, the shared identity model, factories, seeders, and the delivery layer")
            ->expect($internals)
            ->toOnlyBeUsedIn(["{$app}Domain\\{$context}", "{$app}Models", "{$app}Http", 'Database\Factories', 'Database\Seeders']);
    }

    arch("{$context} public surface (Actions, Queries, Exceptions) is only used by the domain, the shared identity model, factories, seeders, and the delivery layer")
        ->expect("{$app}Domain\\{$context}")
        ->toOnlyBeUsedIn(["{$app}Domain", "{$app}Models", "{$app}Http", 'Database\Factories', 'Database\Seeders']);

    if (! array_key_exists($context, $usesFrameworkExceptions)) {
        arch("{$context} reports broken rules as domain exceptions")
            ->expect("{$app}Domain\\{$context}")
            ->not->toUse('Illuminate\Validation\ValidationException');
    }
}

arch('form requests validate only, with no authorize() logic')
    ->expect("{$app}Http\\Requests")
    ->not->toHaveMethod('authorize');

it('authorizes policies through permissions, never role names', function () {
    $layout = ProjectLayout::current();
    $violations = [];

    foreach ($layout->contexts() as $context) {
        foreach ($layout->phpFiles("app/Domain/{$context}/Policies") as $path) {
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
    $violations = [];

    foreach (ProjectLayout::current()->phpFiles('app/Domain') as $path) {
        if (preg_match_all('/\bauth\(|\bAuth::(?:user|id|check|guest|hasUser)\b|\brequest\(|\bsession\(/', (string) file_get_contents($path), $matches)) {
            foreach ($matches[0] as $match) {
                $violations[] = "{$path}: {$match} (take the acting user as a `User \$actor` parameter; the controller passes \$request->user())";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('never lists secrets in resources or shared props', function () {
    $layout = ProjectLayout::current();
    $violations = [];
    $files = [...$layout->phpFiles('app/Http/Resources'), ...array_filter([$layout->appPath('Http/Middleware/HandleInertiaRequests.php')], is_file(...))];

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

it('documents every action, query, and domain exception in its context rule file', function () {
    $layout = ProjectLayout::current();
    $missing = [];

    foreach ($layout->contexts() as $context) {
        $ruleFile = $layout->path('.ai/rules/contexts/'.Str::kebab($context).'.md');
        $rules = is_file($ruleFile) ? (string) file_get_contents($ruleFile) : '';

        foreach (['Actions', 'Queries', 'Exceptions'] as $folder) {
            foreach (glob($layout->appPath("Domain/{$context}/{$folder}/*.php")) ?: [] as $classPath) {
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
    $layout = ProjectLayout::current();
    $violations = [];

    foreach ([...$layout->phpFiles('app/Domain'), ...$layout->phpFiles('app/Models')] as $path) {
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

it('names HTTP and feature test folders after a bounded context', function () {
    $layout = ProjectLayout::current();
    $contexts = $layout->contexts();

    /** @var array<string, array<string, string>> $nonContextFolders */
    $nonContextFolders = (array) $layout->config('architecture.non_context_folders', []);
    $strays = [];

    foreach (['app/Http/Controllers', 'app/Http/Requests', 'tests/Feature'] as $directory) {
        $allowed = [...$contexts, ...array_keys($nonContextFolders[$directory] ?? [])];

        foreach (glob($layout->path($directory.'/*'), GLOB_ONLYDIR) ?: [] as $folder) {
            if (! in_array(basename($folder), $allowed, true)) {
                $strays[] = "{$directory}/".basename($folder).' matches no bounded context. Rename it after a context, or list it in shipkit.architecture.non_context_folders with the reason';
            }
        }
    }

    expect($strays)->toBeEmpty(implode(PHP_EOL, $strays));
});
