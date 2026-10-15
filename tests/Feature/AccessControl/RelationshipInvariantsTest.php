<?php

use App\Domain\AccessControl\Enums\SystemPermission;
use App\Domain\AccessControl\Enums\SystemRole;
use App\Domain\AccessControl\Exceptions\LastSuperAdminCannotBeRemoved;
use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(AccessControlSeeder::class);

    $this->superAdminRole = Role::query()->where('name', SystemRole::SuperAdmin->value)->firstOrFail();
    $this->protectedPermission = Permission::query()->where('name', SystemPermission::ManageRolesResource->value)->firstOrFail();
    $this->soleAdmin = $this->superAdminRole->users()->firstOrFail();
});

describe('a protected role keeps its protected permissions', function () {
    it('refuses every way of taking one away', function (Closure $remove) {
        expect(fn () => $remove($this->superAdminRole, $this->protectedPermission))
            ->toThrow(ProtectedRecordCannotBeChanged::class);

        expect($this->superAdminRole->fresh()->hasPermissionTo($this->protectedPermission->name))->toBeTrue();
    })->with([
        'revokePermissionTo' => fn (Role $role, Permission $permission) => $role->revokePermissionTo($permission),
        'permissions()->detach' => fn (Role $role, Permission $permission) => $role->permissions()->detach($permission->id),
        'permissions()->detach all' => fn (Role $role) => $role->permissions()->detach(),
        'permission roles()->detach' => fn (Role $role, Permission $permission) => $permission->roles()->detach($role->id),
        'syncPermissions' => fn (Role $role) => $role->syncPermissions([]),
        'with model events silenced' => fn (Role $role, Permission $permission) => Role::withoutEvents(fn () => $role->revokePermissionTo($permission)),
    ]);

    it('still lets a protected role lose an ordinary permission', function () {
        $ordinary = Permission::findOrCreate('edit posts', 'web');
        $this->superAdminRole->givePermissionTo($ordinary);

        $this->superAdminRole->revokePermissionTo($ordinary);

        expect($this->superAdminRole->fresh()->hasPermissionTo('edit posts'))->toBeFalse();
    });

    it('still lets an ordinary role lose a protected permission', function () {
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $editor->givePermissionTo($this->protectedPermission);

        $editor->revokePermissionTo($this->protectedPermission);

        expect($editor->fresh()->hasPermissionTo($this->protectedPermission->name))->toBeFalse();
    });
});

describe('the last super admin keeps the role', function () {
    it('refuses every way of taking it away', function (Closure $remove) {
        expect(fn () => $remove($this->soleAdmin, $this->superAdminRole))
            ->toThrow(LastSuperAdminCannotBeRemoved::class);

        expect($this->soleAdmin->fresh()->hasRole(SystemRole::SuperAdmin->value))->toBeTrue();
    })->with([
        'removeRole' => fn (User $user, Role $role) => $user->removeRole($role),
        'roles()->detach' => fn (User $user) => $user->roles()->detach(),
        'role users()->detach' => fn (User $user, Role $role) => $role->users()->detach($user->id),
        'syncRoles' => fn (User $user) => $user->syncRoles([]),
        'with model events silenced' => fn (User $user, Role $role) => User::withoutEvents(fn () => $user->removeRole($role)),
        'deleting the user' => function (User $user) {
            $user->forceFill(['is_protected' => false])->save();
            $user->delete();
        },
        'deleting the user with model events silenced' => function (User $user) {
            $user->forceFill(['is_protected' => false])->save();
            $user->deleteQuietly();
        },
    ]);

    it('lets a super admin go while another one remains', function () {
        $second = User::factory()->create();
        $second->assignRole($this->superAdminRole);

        $this->soleAdmin->removeRole($this->superAdminRole);

        expect($this->soleAdmin->fresh()->hasRole(SystemRole::SuperAdmin->value))->toBeFalse()
            ->and($second->fresh()->hasRole(SystemRole::SuperAdmin->value))->toBeTrue();
    });

    it('lets a user lose an ordinary role', function () {
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $this->soleAdmin->assignRole($editor);

        $this->soleAdmin->removeRole($editor);

        expect($this->soleAdmin->fresh()->hasRole('editor'))->toBeFalse();
    });
});

describe('guards and writes share one transaction', function () {
    it('runs a pivot guard inside a transaction that also holds the delete', function () {
        $levelOutside = DB::transactionLevel();
        $levelInsideGuard = null;

        $holders = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($holders);

        DB::listen(function ($query) use (&$levelInsideGuard) {
            if (str_starts_with(strtolower($query->sql), 'delete') && str_contains($query->sql, 'model_has_roles')) {
                $levelInsideGuard = DB::transactionLevel();
            }
        });

        $user->removeRole($holders);

        expect($levelInsideGuard)->toBeGreaterThan($levelOutside);
    });
});
