<?php

use App\Models\Permission;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

it('requires the assignment permission to edit role assignments', function (array $permissions) {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs(userWithPermissions($permissions))
        ->get(route('roles.permissions.edit', $role))
        ->assertForbidden();
})->with([
    'role resource management' => [['manage roles resource']],
    'permission resource management' => [['manage permissions resource']],
]);

it('renders the permission assignment page for an authorized user', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs(userWithPermissions(['assign permissions']))
        ->get(route('roles.permissions.edit', $role))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/permissions')
            ->where('role.name', 'editor')
            ->has('permissions', 1));
});

it('syncs permissions assigned to a role', function () {
    $user = userWithPermissions(['assign permissions']);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $first = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);
    $second = Permission::create(['name' => 'archive posts', 'guard_name' => 'web']);
    $role->givePermissionTo($first);

    $this->actingAs($user)
        ->put(route('roles.permissions.update', $role), [
            'permissions' => [$second->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('roles.permissions.edit', $role));

    expect($role->refresh()->permissions->pluck('id')->all())->toBe([$second->id]);
});

it('retains protected permissions on a protected role', function () {
    $user = userWithPermissions(['assign permissions']);
    $role = Role::create([
        'name' => 'system-role',
        'guard_name' => 'web',
        'is_protected' => true,
    ]);
    $protectedPermission = Permission::findByName('assign permissions');
    $protectedPermission->forceFill(['is_protected' => true])->save();
    $role->givePermissionTo($protectedPermission);

    $this->actingAs($user)
        ->put(route('roles.permissions.update', $role), ['permissions' => []])
        ->assertSessionHasNoErrors();

    expect($role->refresh()->hasPermissionTo($protectedPermission))->toBeTrue();
});

it('rejects permissions from another guard', function () {
    $user = userWithPermissions(['assign permissions']);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'api permission', 'guard_name' => 'api']);

    $this->actingAs($user)
        ->put(route('roles.permissions.update', $role), [
            'permissions' => [$permission->id],
        ])
        ->assertSessionHasErrors('permissions.0');
});
