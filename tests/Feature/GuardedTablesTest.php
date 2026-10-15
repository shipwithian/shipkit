<?php

use App\Domain\Shared\Eloquent\GuardsInvariants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Cascading deletes on guarded tables that are deliberate, with the reason.
 * Keyed by "table.column". A cascade deletes rows without running their guards, so it is only
 * safe when the parent owns those rows as parts of itself (a team and its memberships).
 *
 * @var array<string, string>
 */
const GUARDED_CASCADES = [];

it('finds the guarded aggregates it protects', function () {
    expect(guardedTables())->toContain('roles', 'permissions', 'users');
});

it('never writes a guarded table around its model', function () {
    $tables = implode('|', array_map(fn (string $table): string => preg_quote($table, '/'), guardedTables()));
    $patterns = [
        "/DB::table\\(\\s*['\"](?:{$tables})['\"]/",
        "/->table\\(\\s*['\"](?:{$tables})['\"]/",
        "/\\b(?:update|delete\\s+from|insert\\s+into)\\s+[\"`]?(?:{$tables})\\b/i",
    ];
    $violations = [];

    foreach ([...phpFiles('app'), ...phpFiles('database/seeders'), ...phpFiles('routes')] as $path) {
        $source = (string) file_get_contents($path);

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $source, $matches)) {
                foreach ($matches[0] as $match) {
                    $violations[] = Str::after($path, base_path().'/').": {$match} (write through the model so GuardsInvariants runs)";
                }
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('never lets a database cascade delete guarded rows without a recorded reason', function () {
    $guarded = guardedTables();
    $parser = (new ParserFactory)->createForHostVersion();
    $finder = new NodeFinder;
    $violations = [];

    foreach (glob(base_path('database/migrations/*.php')) as $migration) {
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

                if (! array_key_exists("{$table}.{$column}", GUARDED_CASCADES)) {
                    $violations[] = basename($migration).": {$table}.{$column} cascades deletes into a guarded table, so deleting the parent skips the guards. Use restrictOnDelete() (or nullOnDelete() when a null column cannot break the invariant), or list \"{$table}.{$column}\" in GUARDED_CASCADES with the reason the parent owns these rows";
                }
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

/**
 * Tables of every model that uses GuardsInvariants.
 *
 * @return list<string>
 */
function guardedTables(): array
{
    return collect([...phpFiles('app/Domain'), ...phpFiles('app/Models')])
        ->map(function (string $path): ?string {
            $relative = Str::of($path)->after(base_path('app').'/')->beforeLast('.php')->replace('/', '\\');

            return 'App\\'.$relative;
        })
        ->filter(fn (string $class): bool => class_exists($class)
            && is_subclass_of($class, Model::class)
            && in_array(GuardsInvariants::class, class_uses_recursive($class), true))
        ->map(fn (string $class): string => (new $class)->getTable())
        ->unique()
        ->values()
        ->all();
}

/**
 * @return list<string>
 */
function phpFiles(string $directory): array
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS));

    return collect($files)
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->filter(fn (string $path): bool => str_ends_with($path, '.php'))
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
