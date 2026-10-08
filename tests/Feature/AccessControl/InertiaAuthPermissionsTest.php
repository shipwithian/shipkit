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

it('shares only the listed user fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->where('auth.user', fn ($shared): bool => collect($shared)->keys()->sort()->values()->all() === [
                'created_at',
                'email',
                'email_verified_at',
                'id',
                'is_protected',
                'name',
                'updated_at',
            ]));
});
