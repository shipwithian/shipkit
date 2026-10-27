<?php

namespace App\Domain\AccessControl\Actions;

use App\Domain\AccessControl\Exceptions\RoleExceedsActorPermissions;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use ShipKit\Core\AccessControl\SystemPermission;
use Spatie\Permission\PermissionRegistrar;

class SyncUserRolesAction
{
    /**
     * Give the user exactly these roles. The actor must hold every permission of each role they add
     * or remove, so nobody can grant themselves more access or strip it from someone who has more.
     * An actor who holds `assign permissions` is exempt: they can already put any permission on their
     * own role, so the check would only add steps, not stop them.
     *
     * @param  array<int, int>  $roleIds
     *
     * @throws RoleExceedsActorPermissions
     */
    public function handle(User $actor, User $user, array $roleIds): void
    {
        $roleIds = array_values(array_unique($roleIds));
        $currentRoleIds = $user->roles()->pluck((new Role)->qualifyColumn('id'))->all();
        $changedRoleIds = [
            ...array_diff($roleIds, $currentRoleIds),
            ...array_diff($currentRoleIds, $roleIds),
        ];

        $this->ensureActorHoldsPermissionsOf($actor, $changedRoleIds);

        DB::transaction(function () use ($user, $roleIds): void {
            $user->roles()->sync($roleIds);

            $user->unsetRelation('roles');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /**
     * @param  array<int, int>  $roleIds
     *
     * @throws RoleExceedsActorPermissions
     */
    private function ensureActorHoldsPermissionsOf(User $actor, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $actorPermissions = $actor->getAllPermissions();

        if ($actorPermissions->contains('name', SystemPermission::AssignPermissions->value)) {
            return;
        }

        $actorPermissionIds = $actorPermissions->pluck('id');

        $exceedsActor = Role::query()
            ->whereKey($roleIds)
            ->with('permissions:id')
            ->get()
            ->contains(fn (Role $role): bool => $role->permissions->pluck('id')->diff($actorPermissionIds)->isNotEmpty());

        if ($exceedsActor) {
            throw new RoleExceedsActorPermissions;
        }
    }
}
