<?php

use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use ShipKit\Core\AccessControl\SystemPermission;

describe('index', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('permissions.index'))->assertRedirect(route('login'));
    });

    it('forbids users without permission resource management', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('permissions.index'))
            ->assertForbidden();
    });

    it('renders permissions for a user who manages the permission resource', function () {
        Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->get(route('permissions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('permissions/index')
                ->has('permissions', count(SystemPermission::cases()) + 1));
    });

    it('gives a permission resource manager every permission ability', function () {
        $publish = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->get(route('permissions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can', ['create' => true])
                ->where('permissions', fn ($permissions): bool => collect($permissions)->firstWhere('id', $publish->id)['can'] === [
                    'update' => true,
                    'delete' => true,
                ]));
    });
});

describe('store', function () {
    it('forbids users without permission resource management', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('permissions.store'), ['name' => 'publish posts'])
            ->assertForbidden();

        expect(Permission::query()->where('name', 'publish posts')->exists())->toBeFalse();
    });

    it('rejects a name that is already taken', function () {
        Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->post(route('permissions.store'), ['name' => ' publish posts '])
            ->assertSessionHasErrors('name');
    });

    it('creates a permission with a trimmed name', function () {
        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->post(route('permissions.store'), ['name' => '  publish posts  '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('permissions.index'));

        expect(Permission::query()->where('name', 'publish posts')->where('guard_name', 'web')->exists())->toBeTrue();
    });
});

describe('update', function () {
    it('renames a permission', function () {
        $permission = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->put(route('permissions.update', $permission), ['name' => 'archive posts'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('permissions.index'));

        expect($permission->refresh()->name)->toBe('archive posts');
    });

    it('does not rename a protected permission', function () {
        $permission = protectedPermission();
        $name = $permission->name;

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->put(route('permissions.update', $permission), ['name' => 'renamed'])
            ->assertSessionHasErrors('name');

        expect($permission->refresh()->name)->toBe($name);
    });
});

describe('destroy', function () {
    it('deletes an unused permission', function () {
        $permission = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->delete(route('permissions.destroy', $permission))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('permissions.index'));

        $this->assertModelMissing($permission);
    });

    it('does not delete a protected permission', function () {
        $permission = protectedPermission();

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->delete(route('permissions.destroy', $permission))
            ->assertSessionHasErrors('permission');

        $this->assertModelExists($permission);
    });

    it('does not delete a permission assigned to a role', function () {
        $permission = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web'])->givePermissionTo($permission);

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->delete(route('permissions.destroy', $permission))
            ->assertSessionHasErrors('permission');

        $this->assertModelExists($permission);
    });

    it('does not delete a permission assigned to a user', function () {
        $permission = Permission::create(['name' => 'archive posts', 'guard_name' => 'web']);
        User::factory()->create()->givePermissionTo($permission);

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->delete(route('permissions.destroy', $permission))
            ->assertSessionHasErrors('permission');

        $this->assertModelExists($permission);
    });
});
