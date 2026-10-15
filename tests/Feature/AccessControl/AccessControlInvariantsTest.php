<?php

use App\Domain\AccessControl\Exceptions\PermissionIsInUse;
use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Exceptions\RoleIsInUse;
use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Domain\Shared\Exceptions\BulkWriteBypassesInvariants;
use App\Models\User;

it('refuses to rename a protected role saved directly on the model', function () {
    $role = Role::create(['name' => 'system', 'guard_name' => 'web']);
    $role->forceFill(['is_protected' => true])->save();

    expect(fn () => $role->update(['name' => 'renamed']))
        ->toThrow(ProtectedRecordCannotBeChanged::class);

    expect($role->fresh()->name)->toBe('system');
});

it('refuses to delete a protected role and keeps its permissions', function () {
    $role = Role::create(['name' => 'system', 'guard_name' => 'web']);
    $role->forceFill(['is_protected' => true])->save();
    $role->givePermissionTo(Permission::findOrCreate('edit posts', 'web'));

    expect(fn () => $role->delete())->toThrow(ProtectedRecordCannotBeChanged::class);

    $this->assertModelExists($role);
    expect($role->fresh()->permissions)->toHaveCount(1);
});

it('refuses to delete a role assigned to users', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($role);

    expect(fn () => $role->delete())->toThrow(RoleIsInUse::class);

    $this->assertModelExists($role);
});

it('refuses to rename a protected permission saved directly on the model', function () {
    $permission = Permission::findOrCreate('system permission', 'web');
    $permission->forceFill(['is_protected' => true])->save();

    expect(fn () => $permission->update(['name' => 'renamed']))
        ->toThrow(ProtectedRecordCannotBeChanged::class);
});

it('refuses to delete a permission assigned to a role', function () {
    $permission = Permission::findOrCreate('edit posts', 'web');
    Role::create(['name' => 'editor', 'guard_name' => 'web'])->givePermissionTo($permission);

    expect(fn () => $permission->delete())->toThrow(PermissionIsInUse::class);

    $this->assertModelExists($permission);
});

it('refuses bulk deletes that would skip the invariant guards', function () {
    $role = Role::create(['name' => 'system', 'guard_name' => 'web']);
    $role->forceFill(['is_protected' => true])->save();

    expect(fn () => Role::query()->whereKey($role->id)->delete())
        ->toThrow(BulkWriteBypassesInvariants::class);

    $this->assertModelExists($role);
});

it('refuses bulk updates and upserts that would skip the invariant guards', function () {
    $permission = Permission::findOrCreate('system permission', 'web');
    $permission->forceFill(['is_protected' => true])->save();

    expect(fn () => Permission::query()->whereKey($permission->id)->update(['name' => 'renamed']))
        ->toThrow(BulkWriteBypassesInvariants::class)
        ->and(fn () => Permission::query()->upsert([['name' => 'renamed', 'guard_name' => 'web']], ['name']))
        ->toThrow(BulkWriteBypassesInvariants::class);

    expect($permission->fresh()->name)->toBe('system permission');
});

it('refuses to rename a protected role even with model events silenced', function () {
    $role = Role::create(['name' => 'system', 'guard_name' => 'web']);
    $role->forceFill(['is_protected' => true])->save();

    expect(fn () => $role->forceFill(['name' => 'renamed'])->saveQuietly())
        ->toThrow(ProtectedRecordCannotBeChanged::class);

    expect($role->fresh()->name)->toBe('system');
});

it('refuses to delete a protected role even with model events silenced', function () {
    $role = Role::create(['name' => 'system', 'guard_name' => 'web']);
    $role->forceFill(['is_protected' => true])->save();

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
