<?php

use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use ShipKit\Core\AccessControl\SystemPermission;
use ShipKit\Core\AccessControl\SystemRole;

it('seeds protected access control records idempotently', function () {
    config()->set('access_control.super_admin', [
        'name' => 'Site Administrator',
        'email' => 'admin@example.test',
        'password' => 'secure-password',
    ]);

    $this->seed(AccessControlSeeder::class);

    $role = Role::findByName(SystemRole::SuperAdmin);
    Permission::create(['name' => 'view reports', 'guard_name' => 'web']);
    $role->givePermissionTo('view reports');

    $this->seed(AccessControlSeeder::class);

    $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
    $role->refresh();

    expect($user->is_protected)->toBeTrue()
        ->and($user->hasRole(SystemRole::SuperAdmin))->toBeTrue()
        ->and($role->is_protected)->toBeTrue()
        ->and($role->hasPermissionTo(SystemPermission::ManageRolesResource))->toBeTrue()
        ->and($role->hasPermissionTo(SystemPermission::ManagePermissionsResource))->toBeTrue()
        ->and($role->hasPermissionTo(SystemPermission::AssignPermissions))->toBeTrue()
        ->and($role->hasPermissionTo(SystemPermission::AssignRoles))->toBeTrue()
        ->and($role->hasPermissionTo('view reports'))->toBeTrue()
        ->and(Role::query()->where('name', SystemRole::SuperAdmin->value)->count())->toBe(1)
        ->and(Permission::query()->where('is_protected', true)->count())->toBe(count(SystemPermission::cases()));
});

it('does not grant implicit abilities to the super admin role', function () {
    config()->set('access_control.super_admin', [
        'name' => 'Site Administrator',
        'email' => 'admin@example.test',
        'password' => 'secure-password',
    ]);

    $this->seed(AccessControlSeeder::class);

    $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
    $role = Role::findByName(SystemRole::SuperAdmin);
    Permission::create(['name' => 'view reports', 'guard_name' => 'web']);

    expect($user->can('view reports'))->toBeFalse();

    $role->givePermissionTo('view reports');
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->can('view reports'))->toBeTrue();

    $role->revokePermissionTo('view reports');
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->can('view reports'))->toBeFalse();
});
