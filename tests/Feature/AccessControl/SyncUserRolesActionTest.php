<?php

use App\Domain\AccessControl\Actions\SyncUserRolesAction;
use App\Domain\AccessControl\Exceptions\RoleExceedsActorPermissions;
use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->actor = userWithPermissions(['assign roles', 'publish posts']);
    $this->user = User::factory()->create();
    $this->publisher = Role::create(['name' => 'publisher', 'guard_name' => 'web']);
    $this->publisher->givePermissionTo('publish posts');
    $this->archiver = Role::create(['name' => 'archiver', 'guard_name' => 'web']);
    $this->archiver->givePermissionTo(Permission::findOrCreate('archive posts', 'web'));
});

it('adds a role whose permissions the actor holds', function () {
    app(SyncUserRolesAction::class)->handle($this->actor, $this->user, [$this->publisher->id]);

    expect($this->user->roles()->pluck('name')->all())->toBe(['publisher']);
});

it('refuses to add a role with a permission the actor lacks', function () {
    app(SyncUserRolesAction::class)->handle($this->actor, $this->user, [$this->archiver->id]);
})->throws(RoleExceedsActorPermissions::class);

it('refuses to remove a role with a permission the actor lacks', function () {
    $this->user->assignRole($this->archiver);

    try {
        app(SyncUserRolesAction::class)->handle($this->actor, $this->user, []);
    } finally {
        expect($this->user->refresh()->hasRole($this->archiver))->toBeTrue();
    }
})->throws(RoleExceedsActorPermissions::class);

it('keeps a role the actor could not grant when it is left unchanged', function () {
    $this->user->assignRole($this->archiver);

    app(SyncUserRolesAction::class)->handle($this->actor, $this->user, [$this->archiver->id, $this->publisher->id]);

    expect($this->user->roles()->pluck('name')->sort()->values()->all())->toBe(['archiver', 'publisher']);
});

it('refuses a change mixing an allowed and a forbidden role without applying either', function () {
    try {
        app(SyncUserRolesAction::class)->handle($this->actor, $this->user, [$this->publisher->id, $this->archiver->id]);
    } finally {
        expect($this->user->roles()->exists())->toBeFalse();
    }
})->throws(RoleExceedsActorPermissions::class);
