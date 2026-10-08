<?php

use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('roles.index'))->assertRedirect(route('login'));
    });

    it('forbids users without role resource management or assignment access', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('roles.index'))
            ->assertForbidden();
    });

    it('renders roles for a user who manages the role resource', function () {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles/index')
                ->has('roles', 2));
    });

    it('lets assignment-only users list roles', function () {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['assign permissions']))
            ->get(route('roles.index'))
            ->assertOk();
    });
});

describe('store', function () {
    it('forbids users without role resource management', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('roles.store'), ['name' => 'editor'])
            ->assertForbidden();

        expect(Role::query()->where('name', 'editor')->exists())->toBeFalse();
    });

    it('forbids assignment-only users', function () {
        $this->actingAs(userWithPermissions(['assign permissions']))
            ->post(route('roles.store'), ['name' => 'publisher'])
            ->assertForbidden();

        expect(Role::query()->where('name', 'publisher')->exists())->toBeFalse();
    });

    it('rejects a name that is already taken', function () {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->post(route('roles.store'), ['name' => ' editor '])
            ->assertSessionHasErrors('name');
    });

    it('creates a role with a trimmed name', function () {
        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->post(route('roles.store'), ['name' => '  editor  '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('roles.index'));

        expect(Role::query()->where('name', 'editor')->where('guard_name', 'web')->exists())->toBeTrue();
    });
});

describe('update', function () {
    it('forbids assignment-only users', function () {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['assign permissions']))
            ->put(route('roles.update', $role), ['name' => 'publisher'])
            ->assertForbidden();

        expect($role->refresh()->name)->toBe('editor');
    });

    it('renames a role', function () {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->put(route('roles.update', $role), ['name' => 'publisher'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('roles.index'));

        expect($role->refresh()->name)->toBe('publisher');
    });

    it('does not rename a protected role', function () {
        $role = Role::create([
            'name' => 'system-role',
            'guard_name' => 'web',
            'is_protected' => true,
        ]);

        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->put(route('roles.update', $role), ['name' => 'renamed'])
            ->assertSessionHasErrors('name');

        expect($role->refresh()->name)->toBe('system-role');
    });
});

describe('destroy', function () {
    it('forbids assignment-only users', function () {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['assign permissions']))
            ->delete(route('roles.destroy', $role))
            ->assertForbidden();

        $this->assertModelExists($role);
    });

    it('deletes an unused role', function () {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->delete(route('roles.destroy', $role))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('roles.index'));

        $this->assertModelMissing($role);
    });

    it('does not delete a protected role', function () {
        $role = Role::create([
            'name' => 'system-role',
            'guard_name' => 'web',
            'is_protected' => true,
        ]);

        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->delete(route('roles.destroy', $role))
            ->assertSessionHasErrors('role');

        $this->assertModelExists($role);
    });

    it('does not delete a role assigned to users', function () {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($role);

        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->delete(route('roles.destroy', $role))
            ->assertSessionHasErrors('role');

        $this->assertModelExists($role);
    });
});
