<?php

use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from role management', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

it('forbids users without role resource management or assignment access', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('forbids creating roles without role resource management', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('roles.store'), ['name' => 'editor'])
        ->assertForbidden();

    expect(Role::query()->where('name', 'editor')->exists())->toBeFalse();
});

it('renders roles for an authorized user', function () {
    Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs(userWithPermissions(['manage roles resource']))
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/index')
            ->has('roles', 2));
});

it('lets assignment-only users list roles but not manage the role resource', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $user = userWithPermissions(['assign permissions']);

    $this->actingAs($user)
        ->get(route('roles.index'))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('roles.store'), ['name' => 'publisher'])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('roles.update', $role), ['name' => 'publisher'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect($role->refresh()->name)->toBe('editor');
});

it('creates updates and deletes an unused role', function () {
    $user = userWithPermissions(['manage roles resource']);

    $this->actingAs($user)
        ->post(route('roles.store'), ['name' => '  editor  '])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('roles.index'));

    $role = Role::findByName('editor');

    $this->actingAs($user)
        ->put(route('roles.update', $role), ['name' => 'publisher'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('roles.index'));

    expect($role->refresh()->name)->toBe('publisher');

    $this->actingAs($user)
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('roles.index'));

    $this->assertModelMissing($role);
});

it('validates role names', function () {
    $user = userWithPermissions(['manage roles resource']);
    Role::create(['name' => 'editor', 'guard_name' => 'web']);

    $this->actingAs($user)
        ->post(route('roles.store'), ['name' => ' editor '])
        ->assertSessionHasErrors('name');
});

it('does not rename or delete protected roles', function () {
    $user = userWithPermissions(['manage roles resource']);
    $role = Role::create([
        'name' => 'system-role',
        'guard_name' => 'web',
        'is_protected' => true,
    ]);

    $this->actingAs($user)
        ->put(route('roles.update', $role), ['name' => 'renamed'])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors('role');

    expect($role->refresh()->name)->toBe('system-role');
});

it('does not delete roles assigned to users', function () {
    $manager = userWithPermissions(['manage roles resource']);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($role);

    $this->actingAs($manager)
        ->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors('role');

    $this->assertModelExists($role);
});
