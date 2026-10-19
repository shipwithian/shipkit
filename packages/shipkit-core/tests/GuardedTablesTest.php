<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ShipKit\Core\Architecture\ProjectLayout;
use ShipKit\Core\Eloquent\BuilderMethods;
use ShipKit\Core\Eloquent\GuardsInvariants;
use ShipKit\Core\GuardedModelMap;

uses(TestCase::class);

it('finds the guarded aggregates it protects', function () {
    $models = app(GuardedModelMap::class);

    expect(guardedTables())->toContain(
        (new ($models->role()))->getTable(),
        (new ($models->permission()))->getTable(),
        (new ($models->user()))->getTable(),
    );
});

it('never writes a guarded table around its model', function () {
    $layout = ProjectLayout::current();
    $tables = implode('|', array_map(fn (string $table): string => preg_quote($table, '/'), guardedTables()));
    $patterns = [
        "/DB::table\\(\\s*['\"](?:{$tables})['\"]/",
        "/->table\\(\\s*['\"](?:{$tables})['\"]/",
        "/\\b(?:update|delete\\s+from|insert\\s+into)\\s+[\"`]?(?:{$tables})\\b/i",
    ];
    $violations = [];

    foreach ([...$layout->phpFiles('app'), ...$layout->phpFiles('database/seeders'), ...$layout->phpFiles('routes')] as $path) {
        $source = (string) file_get_contents($path);

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $source, $matches)) {
                foreach ($matches[0] as $match) {
                    $violations[] = Str::after($path, $layout->path().'/').": {$match} (write through the model so GuardsInvariants runs)";
                }
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('never writes through the base query builder', function () {
    $layout = ProjectLayout::current();
    $writes = implode('|', [...BuilderMethods::GUARDED, ...BuilderMethods::REJECTED]);
    $violations = [];

    foreach ([...$layout->phpFiles('app'), ...$layout->phpFiles('database/seeders'), ...$layout->phpFiles('routes')] as $path) {
        if (preg_match_all("/->(?:toBase|getQuery)\\(\\)\\s*->\\s*(?:{$writes})\\(/", (string) file_get_contents($path), $matches)) {
            foreach ($matches[0] as $match) {
                $violations[] = Str::after($path, $layout->path().'/').": {$match} writes through the base query builder, which skips a guarded model's invariants. Load the record and save or delete it through the model; for an unguarded model, call the write on its Eloquent query directly";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('never lets a database cascade delete guarded rows without a recorded reason', function () {
    $guarded = guardedTables();

    /** @var array<string, string> $recordedCascades */
    $recordedCascades = (array) config('shipkit.guarded_cascades', []);
    $parser = (new ParserFactory)->createForHostVersion();
    $finder = new NodeFinder;
    $violations = [];

    foreach (glob(ProjectLayout::current()->path('database/migrations/*.php')) ?: [] as $migration) {
        $statements = $parser->parse((string) file_get_contents($migration)) ?? [];

        $schemaCalls = $finder->find($statements, fn (Node $node): bool => $node instanceof Node\Expr\StaticCall
            && $node->class instanceof Node\Name
            && $node->class->getLast() === 'Schema'
            && $node->name instanceof Node\Identifier
            && in_array($node->name->toString(), ['create', 'table'], true)
            && ($node->args[0]->value ?? null) instanceof Node\Scalar\String_
            && in_array($node->args[0]->value->value, $guarded, true));

        foreach ($schemaCalls as $call) {
            $table = $call->args[0]->value->value;

            foreach ($finder->find([$call->args[1]->value ?? $call], fn (Node $node): bool => $node instanceof Node\Expr\MethodCall && cascadesOnDelete($node)) as $cascade) {
                $column = cascadeColumn($cascade);

                if (! array_key_exists("{$table}.{$column}", $recordedCascades)) {
                    $violations[] = basename($migration).": {$table}.{$column} cascades deletes into a guarded table, so deleting the parent skips the guards. Use restrictOnDelete() (or nullOnDelete() when a null column cannot break the invariant), or list \"{$table}.{$column}\" in shipkit.guarded_cascades in config/shipkit.php with the reason the parent owns these rows";
                }
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

/**
 * Tables of every guarded model: the ones mapped in config/shipkit.php, and every model under
 * the application's Domain and Models folders that uses GuardsInvariants.
 *
 * @return list<string>
 */
function guardedTables(): array
{
    $layout = ProjectLayout::current();
    $models = app(GuardedModelMap::class);
    $discovered = array_map($layout->classForAppFile(...), [...$layout->phpFiles('app/Domain'), ...$layout->phpFiles('app/Models')]);

    return collect([$models->role(), $models->permission(), $models->user(), ...$discovered])
        ->filter(fn (string $class): bool => class_exists($class)
            && is_subclass_of($class, Model::class)
            && in_array(GuardsInvariants::class, class_uses_recursive($class), true))
        ->map(fn (string $class): string => (new $class)->getTable())
        ->unique()
        ->values()
        ->all();
}

function cascadesOnDelete(Node\Expr\MethodCall $call): bool
{
    if (! $call->name instanceof Node\Identifier) {
        return false;
    }

    return $call->name->toString() === 'cascadeOnDelete'
        || ($call->name->toString() === 'onDelete' && ($call->args[0]->value ?? null) instanceof Node\Scalar\String_ && $call->args[0]->value->value === 'cascade');
}

function cascadeColumn(Node\Expr\MethodCall $call): string
{
    $node = $call;

    while ($node instanceof Node\Expr\MethodCall) {
        if ($node->name instanceof Node\Identifier
            && in_array($node->name->toString(), ['foreignId', 'foreignUuid', 'foreignUlid', 'foreign'], true)
            && ($node->args[0]->value ?? null) instanceof Node\Scalar\String_) {
            return $node->args[0]->value->value;
        }

        $node = $node->var;
    }

    return '?';
}
