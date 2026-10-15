<?php

namespace App\Domain\AccessControl\Actions;

use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class SyncRolePermissionsAction
{
    /**
     * @param  array<int, int>  $permissionIds
     */
    public function handle(Role $role, array $permissionIds): void
    {
        if ($role->is_protected) {
            $permissionIds = [
                ...$permissionIds,
                ...Permission::query()->where('is_protected', true)->pluck('id')->all(),
            ];
        }

        DB::transaction(function () use ($role, $permissionIds): void {
            $role->permissions()->sync(array_values(array_unique($permissionIds)));

            $role->unsetRelation('permissions');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
