<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from permission management', function () {
    $this->get(route('permissions.index'))->assertRedirect(route('login'));
});

it('forbids users without permission resource management', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('permissions.index'))
        ->assertForbidden();
});

it('forbids creating permissions without permission resource management', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('permissions.store'), ['name' => 'publish posts'])
        ->assertForbidden();

    expect(Permission::query()->where('name', 'publish posts')->exists())->toBeFalse();
});

it('renders permissions for an authorized user', function () {
    Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);

    $this->actingAs(userWithPermissions(['manage permissions resource']))
        ->get(route('permissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('permissions/index')
            ->has('permissions', 2));
});

it('creates updates and deletes an unused permission', function () {
    $user = userWithPermissions(['manage permissions resource']);

    $this->actingAs($user)
        ->post(route('permissions.store'), ['name' => '  publish posts  '])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('permissions.index'));

    $permission = Permission::findByName('publish posts');

    $this->actingAs($user)
        ->put(route('permissions.update', $permission), ['name' => 'archive posts'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('permissions.index'));

    expect($permission->refresh()->name)->toBe('archive posts');

    $this->actingAs($user)
        ->delete(route('permissions.destroy', $permission))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('permissions.index'));

    $this->assertModelMissing($permission);
});

it('validates permission names', function () {
    $user = userWithPermissions(['manage permissions resource']);
    Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);

    $this->actingAs($user)
        ->post(route('permissions.store'), ['name' => ' publish posts '])
        ->assertSessionHasErrors('name');
});

it('does not rename or delete protected permissions', function () {
    $user = userWithPermissions(['manage permissions resource']);
    $permission = Permission::create([
        'name' => 'system permission',
        'guard_name' => 'web',
        'is_protected' => true,
    ]);

    $this->actingAs($user)
        ->put(route('permissions.update', $permission), ['name' => 'renamed'])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->delete(route('permissions.destroy', $permission))
        ->assertSessionHasErrors('permission');

    expect($permission->refresh()->name)->toBe('system permission');
});

it('does not delete permissions assigned to roles or users', function () {
    $manager = userWithPermissions(['manage permissions resource']);
    $rolePermission = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);
    $userPermission = Permission::create(['name' => 'archive posts', 'guard_name' => 'web']);
    Role::create(['name' => 'editor', 'guard_name' => 'web'])->givePermissionTo($rolePermission);
    User::factory()->create()->givePermissionTo($userPermission);

    $this->actingAs($manager)
        ->delete(route('permissions.destroy', $rolePermission))
        ->assertSessionHasErrors('permission');

    $this->actingAs($manager)
        ->delete(route('permissions.destroy', $userPermission))
        ->assertSessionHasErrors('permission');

    $this->assertModelExists($rolePermission);
    $this->assertModelExists($userPermission);
});
