<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('maps permission abilities to permission resource management', function (
    array $permissions,
    bool $expected,
) {
    $user = $permissions === []
        ? User::factory()->create()
        : userWithPermissions($permissions);
    $permission = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Permission::class))->toBe($expected)
        ->and($gate->allows('create', Permission::class))->toBe($expected)
        ->and($gate->allows('update', $permission))->toBe($expected)
        ->and($gate->allows('delete', $permission))->toBe($expected);
})->with([
    'no permissions' => [[], false],
    'role resource management' => [['manage roles resource'], false],
    'permission assignment' => [['assign permissions'], false],
    'permission resource management' => [['manage permissions resource'], true],
    'all system permissions' => [[
        'manage roles resource',
        'manage permissions resource',
        'assign permissions',
    ], true],
]);
