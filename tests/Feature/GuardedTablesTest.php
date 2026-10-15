<?php

use App\Domain\Shared\Eloquent\GuardsInvariants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
