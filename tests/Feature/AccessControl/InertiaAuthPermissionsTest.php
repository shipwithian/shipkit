<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares permissions inherited through roles', function () {
    $user = userWithPermissions([
        'manage roles resource',
        'manage permissions resource',
        'assign permissions',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', [
                'assign permissions',
                'manage permissions resource',
                'manage roles resource',
            ]));
});

it('shares only currently assigned permissions', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', ['publish posts']));

    $role->revokePermissionTo($permission);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', []));
});
