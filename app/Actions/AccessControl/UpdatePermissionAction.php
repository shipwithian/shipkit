<?php

namespace App\Actions\AccessControl;

use App\Exceptions\ProtectedRecordCannotBeChanged;
use App\Models\Permission;

class UpdatePermissionAction
{
    /**
     * @throws ProtectedRecordCannotBeChanged
     */
    public function handle(Permission $permission, string $name): Permission
    {
        $permission->update(['name' => $name]);

        return $permission;
    }
}
