<?php

use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    });

    it('forbids users without role assignment access', function () {
        $this->actingAs(userWithPermissions(['assign permissions']))
            ->get(route('users.index'))
            ->assertForbidden();
    });

    it('lists users with their roles and the assignable roles', function () {
        $actor = userWithPermissions(['assign roles']);
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $member = User::factory()->create(['name' => 'Zed Member']);
        $member->assignRole($editor);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/index')
                ->has('users', User::query()->count())
                ->where('users', fn ($users): bool => collect($users)->firstWhere('id', $member->id)['role_ids'] === [$editor->id])
                ->where('users.0', fn ($user): bool => collect($user)->keys()->sort()->values()->all() === ['email', 'id', 'name', 'role_ids'])
                ->where('pagination.current_page', 1)
                ->where('roles', fn ($roles): bool => collect($roles)->pluck('name')->contains('editor')));
    });
});

describe('update', function () {
    it('redirects guests to the login page', function () {
        $user = User::factory()->create();

        $this->put(route('users.roles.update', $user), ['roles' => []])
            ->assertRedirect(route('login'));
    });

    it('forbids users without role assignment access', function () {
        $user = User::factory()->create();
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['assign permissions']))
            ->put(route('users.roles.update', $user), ['roles' => [$editor->id]])
            ->assertForbidden();

        expect($user->roles()->exists())->toBeFalse();
    });

    it('rejects a missing roles list', function () {
        $user = User::factory()->create();

        $this->actingAs(userWithPermissions(['assign roles']))
            ->put(route('users.roles.update', $user), [])
            ->assertSessionHasErrors('roles');
    });

    it('rejects roles from another guard', function () {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'api role', 'guard_name' => 'api']);

        $this->actingAs(userWithPermissions(['assign roles']))
            ->put(route('users.roles.update', $user), ['roles' => [$role->id]])
            ->assertSessionHasErrors('roles.0');
    });

    it('replaces the roles a user holds', function () {
        $user = User::factory()->create();
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $viewer = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $user->assignRole($editor);

        $this->actingAs(userWithPermissions(['assign roles']))
            ->from(route('users.index'))
            ->put(route('users.roles.update', $user), ['roles' => [$viewer->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        expect($user->roles()->pluck('name')->all())->toBe(['viewer']);
    });

    it('refuses to let an actor grant themselves super-admin', function () {
        $actor = userWithPermissions(['assign roles']);

        $this->actingAs($actor)
            ->put(route('users.roles.update', $actor), [
                'roles' => [...$actor->roles()->pluck('id')->all(), protectedRole()->id],
            ])
            ->assertSessionHasErrors(['roles' => 'You can only assign or remove roles whose permissions you hold.']);

        expect($actor->hasRole(protectedRole()))->toBeFalse();
    });

    it('lets a super admin assign a role whose permissions super-admin lacks', function () {
        $user = User::factory()->create();
        $publisher = Role::create(['name' => 'publisher', 'guard_name' => 'web']);
        $publisher->givePermissionTo(Permission::findOrCreate('publish posts', 'web'));

        $this->actingAs(protectedUser())
            ->from(route('users.index'))
            ->put(route('users.roles.update', $user), ['roles' => [$publisher->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        expect($user->hasRole($publisher))->toBeTrue();
    });

    it('refuses to remove the last super admin', function () {
        $superAdmin = protectedUser();

        $this->actingAs($superAdmin)
            ->put(route('users.roles.update', $superAdmin), ['roles' => []])
            ->assertSessionHasErrors(['roles' => 'The last super admin cannot lose that role or be deleted.']);

        expect($superAdmin->refresh()->hasRole(protectedRole()))->toBeTrue();
    });
});
