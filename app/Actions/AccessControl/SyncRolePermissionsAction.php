<?php

namespace App\Actions\AccessControl;

use App\Models\Permission;
use App\Models\Role;

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

        $role->syncPermissions(array_values(array_unique($permissionIds)));
    }
}
