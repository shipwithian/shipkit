<?php

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;

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
        ->and($role->hasPermissionTo('view reports'))->toBeTrue()
        ->and(Role::query()->where('name', SystemRole::SuperAdmin->value)->count())->toBe(1)
        ->and(Permission::query()->where('is_protected', true)->count())->toBe(3);
});

it('renames legacy permissions without losing assignments', function () {
    config()->set('access_control.super_admin', [
        'name' => 'Site Administrator',
        'email' => 'admin@example.test',
        'password' => 'secure-password',
    ]);

    $role = Role::create(['name' => 'role manager', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $legacyRolePermission = Permission::create(['name' => 'manage roles', 'guard_name' => 'web']);
    $legacyPermissionPermission = Permission::create(['name' => 'manage permissions', 'guard_name' => 'web']);
    $role->givePermissionTo($legacyRolePermission);
    $user->givePermissionTo($legacyPermissionPermission);

    $this->seed(AccessControlSeeder::class);

    expect(Permission::query()->whereIn('name', ['manage roles', 'manage permissions'])->exists())->toBeFalse()
        ->and($legacyRolePermission->refresh()->name)->toBe(SystemPermission::ManageRolesResource->value)
        ->and($legacyPermissionPermission->refresh()->name)->toBe(SystemPermission::ManagePermissionsResource->value)
        ->and($role->refresh()->hasPermissionTo(SystemPermission::ManageRolesResource))->toBeTrue()
        ->and($user->refresh()->can(SystemPermission::ManagePermissionsResource->value))->toBeTrue();

    $this->seed(AccessControlSeeder::class);

    expect(Permission::query()->where('name', SystemPermission::ManageRolesResource->value)->count())->toBe(1)
        ->and(Permission::query()->where('name', SystemPermission::ManagePermissionsResource->value)->count())->toBe(1)
        ->and(Permission::query()->where('name', SystemPermission::AssignPermissions->value)->count())->toBe(1);
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
