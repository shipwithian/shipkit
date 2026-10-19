<?php

use App\Domain\AccessControl\Exceptions\PermissionIsInUse;
use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Exceptions\RoleIsInUse;
use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use ShipKit\Core\Exceptions\BulkWriteBypassesInvariants;

it('refuses to rename a protected role saved directly on the model', function () {
    $role = protectedRole();

    expect(fn () => $role->update(['name' => 'renamed']))
        ->toThrow(ProtectedRecordCannotBeChanged::class);

    expect($role->fresh()->name)->toBe('super-admin');
});

it('refuses to delete a protected role and keeps its permissions', function () {
    $role = protectedRole();
    $permissionCount = $role->permissions()->count();

    expect(fn () => $role->delete())->toThrow(ProtectedRecordCannotBeChanged::class);

    $this->assertModelExists($role);
    expect($role->fresh()->permissions)->toHaveCount($permissionCount);
});

it('refuses to delete a role assigned to users', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($role);

    expect(fn () => $role->delete())->toThrow(RoleIsInUse::class);

    $this->assertModelExists($role);
});

it('refuses to rename a protected permission saved directly on the model', function () {
    $permission = protectedPermission();
    $name = $permission->name;

    expect(fn () => $permission->update(['name' => 'renamed']))
        ->toThrow(ProtectedRecordCannotBeChanged::class);

    expect($permission->fresh()->name)->toBe($name);
});

it('refuses to delete a permission assigned to a role', function () {
    $permission = Permission::findOrCreate('edit posts', 'web');
    Role::create(['name' => 'editor', 'guard_name' => 'web'])->givePermissionTo($permission);

    expect(fn () => $permission->delete())->toThrow(PermissionIsInUse::class);

    $this->assertModelExists($permission);
});

it('refuses bulk deletes that would skip the invariant guards', function () {
    $role = protectedRole();

    expect(fn () => Role::query()->whereKey($role->id)->delete())
        ->toThrow(BulkWriteBypassesInvariants::class);

    $this->assertModelExists($role);
});

it('refuses bulk updates and upserts that would skip the invariant guards', function () {
    $permission = protectedPermission();
    $name = $permission->name;

    expect(fn () => Permission::query()->whereKey($permission->id)->update(['name' => 'renamed']))
        ->toThrow(BulkWriteBypassesInvariants::class)
        ->and(fn () => Permission::query()->upsert([['name' => 'renamed', 'guard_name' => 'web']], ['name']))
        ->toThrow(BulkWriteBypassesInvariants::class);

    expect($permission->fresh()->name)->toBe($name);
});

it('refuses to rename a protected role even with model events silenced', function () {
    $role = protectedRole();

    expect(fn () => $role->forceFill(['name' => 'renamed'])->saveQuietly())
        ->toThrow(ProtectedRecordCannotBeChanged::class);

    expect($role->fresh()->name)->toBe('super-admin');
});

it('refuses to delete a protected role even with model events silenced', function () {
    $role = protectedRole();

    expect(fn () => $role->deleteQuietly())->toThrow(ProtectedRecordCannotBeChanged::class)
        ->and(fn () => Role::withoutEvents(fn () => $role->delete()))->toThrow(ProtectedRecordCannotBeChanged::class);

    $this->assertModelExists($role);
});

it('refuses to delete an assigned permission even with model events silenced', function () {
    $permission = Permission::findOrCreate('edit posts', 'web');
    Role::create(['name' => 'editor', 'guard_name' => 'web'])->givePermissionTo($permission);

    expect(fn () => $permission->deleteQuietly())->toThrow(PermissionIsInUse::class);

    $this->assertModelExists($permission);
});

describe('the protected marker never changes after creation', function () {
    it('refuses to remove protection', function (Closure $record, bool $silenced) {
        $model = $record();
        $save = fn () => $model->forceFill(['is_protected' => false])->save();

        expect($silenced ? fn () => $model::withoutEvents($save) : $save)->toThrow(ProtectedRecordCannotBeChanged::class);

        expect($model->fresh()->is_protected)->toBeTrue();
    })->with([
        'a role' => fn () => protectedRole(),
        'a permission' => fn () => protectedPermission(),
        'a user' => fn () => protectedUser(),
    ])->with([
        'with model events' => false,
        'with model events silenced' => true,
    ]);

    it('refuses to protect an existing record', function (Closure $record, bool $silenced) {
        $model = $record();
        $save = fn () => $model->forceFill(['is_protected' => true])->save();

        expect($silenced ? fn () => $model::withoutEvents($save) : $save)->toThrow(ProtectedRecordCannotBeChanged::class);

        expect($model->fresh()->is_protected)->toBeFalse();
    })->with([
        'a role' => fn () => Role::create(['name' => 'editor', 'guard_name' => 'web']),
        'a permission' => fn () => Permission::create(['name' => 'edit posts', 'guard_name' => 'web']),
        'a user' => fn () => User::factory()->create(),
    ])->with([
        'with model events' => false,
        'with model events silenced' => true,
    ]);

    it('still lets a protected user edit their profile', function () {
        $user = protectedUser();

        $user->update(['name' => 'New Name']);

        expect($user->fresh())
            ->name->toBe('New Name')
            ->is_protected->toBeTrue();
    });
});
