<?php

use ShipKit\Core\Architecture\ProjectLayout;

it('gates the UI on server-computed abilities, never permission names', function () {
    $layout = ProjectLayout::current();
    $violations = [];

    foreach ($layout->frontendFiles() as $relative) {
        foreach (permissionNameChecks((string) file_get_contents($layout->path($relative))) as $line => $check) {
            $violations[] = "{$relative}:{$line} decides visibility from a permission name (`{$check}`). Pass the page an ability computed with the Gate from the policy the controller authorizes, such as `can.update` in its props class, and read that instead";
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('recognizes the ways a page can check a permission name', function (string $source) {
    expect(permissionNameChecks($source))->not->toBeEmpty();
})->with([
    'includes' => ["auth.permissions.includes('manage roles resource')"],
    'optional chaining' => ['user?.permissions?.includes("publish posts")'],
    'some over a list' => ["['manage roles resource', 'assign permissions'].includes(permission)"],
    'the shared permissions prop' => ['const { permissions } = auth; if (auth.permissions.length) {}'],
]);

it('leaves checks that are not about permission names alone', function (string $source) {
    expect(permissionNameChecks($source))->toBeEmpty();
})->with([
    'a server-computed ability' => ['{can.update && <EditRoleDialog role={role} />}'],
    'a form value holding permission ids' => ['form.data.permissions.includes(permissionId)'],
    'a list of permissions to render' => ['permissions.map((permission) => permission.name)'],
]);

/**
 * Permission-name checks in a frontend source, keyed by line number.
 *
 * @return array<int, string>
 */
function permissionNameChecks(string $source): array
{
    $patterns = [
        // permissions.includes('name'), permissions?.some("name"), and similar calls with a literal name.
        '/\bpermissions\s*\??\.\s*(?:includes|indexOf|some|every|find|findIndex|filter)\s*\(\s*[\'"`]/',
        // ['name', 'other'].includes(permission)
        '/\[\s*[\'"`][^\'"`]+[\'"`](?:\s*,\s*[\'"`][^\'"`]+[\'"`])*\s*,?\s*\]\s*\.\s*includes\s*\(\s*permission\b/',
        // The shared permission list, which the server no longer sends.
        '/\bauth\s*\??\.\s*permissions\b/',
    ];

    $checks = [];

    foreach (explode("\n", $source) as $index => $line) {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                $checks[$index + 1] = trim($line);

                break;
            }
        }
    }

    return $checks;
}
