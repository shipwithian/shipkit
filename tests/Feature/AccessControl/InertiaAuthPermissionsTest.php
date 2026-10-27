<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares no navigation abilities with a guest', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('auth.can', ['viewRoles' => false, 'viewPermissions' => false, 'viewUsers' => false]));
});

it('shares no navigation abilities with a user without access control permissions', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can', ['viewRoles' => false, 'viewPermissions' => false, 'viewUsers' => false]));
});

it('shares the navigation abilities each permission grants through a role', function (array $permissions, array $abilities) {
    $this->actingAs(userWithPermissions($permissions))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can', $abilities));
})->with([
    'role resource management' => [['manage roles resource'], ['viewRoles' => true, 'viewPermissions' => false, 'viewUsers' => false]],
    'permission assignment' => [['assign permissions'], ['viewRoles' => true, 'viewPermissions' => false, 'viewUsers' => false]],
    'permission resource management' => [['manage permissions resource'], ['viewRoles' => false, 'viewPermissions' => true, 'viewUsers' => false]],
    'role assignment' => [['assign roles'], ['viewRoles' => false, 'viewPermissions' => false, 'viewUsers' => true]],
]);

it('stops sharing an ability once its permission is revoked', function () {
    $user = userWithPermissions(['manage permissions resource']);
    $role = $user->roles()->sole();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.viewPermissions', true));

    $role->revokePermissionTo('manage permissions resource');
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.viewPermissions', false));
});

it('never shares permission names', function () {
    $this->actingAs(userWithPermissions(['manage roles resource']))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth', fn ($auth): bool => collect($auth)->keys()->sort()->values()->all() === ['can', 'user']));
});

it('shares only the listed user fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.is_protected', false)
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
