<?php

use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('maps role abilities to the required permissions', function (
    array $permissions,
    bool $canViewRoles,
    bool $canManageRoles,
    bool $canAssignPermissions,
    bool $canAssignRoles,
) {
    $user = $permissions === []
        ? User::factory()->create()
        : userWithPermissions($permissions);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Role::class))->toBe($canViewRoles)
        ->and($gate->allows('create', Role::class))->toBe($canManageRoles)
        ->and($gate->allows('update', $role))->toBe($canManageRoles)
        ->and($gate->allows('delete', $role))->toBe($canManageRoles)
        ->and($gate->allows('assignPermissions', $role))->toBe($canAssignPermissions)
        ->and($gate->allows('assignToUsers', Role::class))->toBe($canAssignRoles);
})->with([
    'no permissions' => [[], false, false, false, false],
    'role resource management' => [['manage roles resource'], true, true, false, false],
    'permission resource management' => [['manage permissions resource'], false, false, false, false],
    'permission assignment' => [['assign permissions'], true, false, true, false],
    'role assignment' => [['assign roles'], false, false, false, true],
    'all system permissions' => [[
        'manage roles resource',
        'manage permissions resource',
        'assign permissions',
        'assign roles',
    ], true, true, true, true],
]);
