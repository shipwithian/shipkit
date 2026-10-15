<?php

use App\Domain\AccessControl\Models\Permission;
use Illuminate\Support\Str;

it('finds the permission checks it protects', function () {
    expect(frontendPermissionChecks())->not->toBeEmpty('No permission checks were found in resources/js, so this test would pass vacuously. Update its patterns to match how pages check auth.permissions.');
});

it('only checks permissions that the seeders create', function () {
    $this->seed();

    $known = Permission::query()->pluck('name')->all();
    $unknown = [];

    foreach (frontendPermissionChecks() as $permission => $files) {
        if (! in_array($permission, $known, true)) {
            $unknown[] = "'{$permission}' in ".implode(', ', $files).' is not a seeded permission, so it never matches and hides the UI behind it (check the spelling, or seed the permission)';
        }
    }

    expect($unknown)->toBeEmpty(implode(PHP_EOL, $unknown));
});

/**
 * Permission names the frontend tests with `permissions.includes('x')` or `['x', 'y'].includes(permission)`.
 *
 * @return array<string, list<string>>
 */
function frontendPermissionChecks(): array
{
    $checks = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/js'), FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = $file->getPathname();
        $relative = Str::after($path, base_path().'/');

        if (! Str::endsWith($path, ['.ts', '.tsx']) || Str::startsWith($relative, ['resources/js/actions/', 'resources/js/routes/', 'resources/js/wayfinder/'])) {
            continue;
        }

        $source = (string) file_get_contents($path);

        preg_match_all("/permissions\\.includes\\(\\s*'([^']+)'\\s*\\)/", $source, $direct);
        preg_match_all("/\\[((?:\\s*'[^']+'\\s*,?)+)\\s*\\]\\.includes\\(\\s*permission\\s*\\)/", $source, $lists);

        $names = $direct[1];

        foreach ($lists[1] as $list) {
            preg_match_all("/'([^']+)'/", $list, $quoted);
            $names = [...$names, ...$quoted[1]];
        }

        foreach ($names as $name) {
            $checks[$name][] = $relative;
        }
    }

    return array_map(fn (array $files): array => array_values(array_unique($files)), $checks);
}
