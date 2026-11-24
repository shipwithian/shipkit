<?php

use App\Models\User;

it('serves the access-control pages under settings', function (string $name, array $parameters, string $path) {
    expect(route($name, $parameters, absolute: false))->toBe($path);
})->with([
    'roles' => ['roles.index', [], '/settings/roles'],
    'a role\'s permissions' => ['roles.permissions.edit', ['role' => 7], '/settings/roles/7/permissions'],
    'permissions' => ['permissions.index', [], '/settings/permissions'],
    'users' => ['users.index', [], '/settings/users'],
]);

it('sends the old addresses to their new ones, so links and bookmarks keep working', function (string $old, string $new) {
    $this->actingAs(User::factory()->create())
        ->get($old)
        ->assertStatus(301)
        ->assertRedirect($new);
})->with([
    'roles' => ['/roles', '/settings/roles'],
    'a role\'s permissions' => ['/roles/7/permissions', '/settings/roles/7/permissions'],
    'permissions' => ['/permissions', '/settings/permissions'],
    'users' => ['/users', '/settings/users'],
]);

it('keeps the pages behind their policies at the new address', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('renders the role list for someone allowed to see it', function () {
    $this->actingAs(userWithPermissions(['manage roles resource']))
        ->get('/settings/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('roles/index'));
});
