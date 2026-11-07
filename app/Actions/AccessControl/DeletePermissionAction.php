<?php

namespace App\Actions\AccessControl;

use App\Exceptions\PermissionIsInUse;
use App\Exceptions\ProtectedRecordCannotBeChanged;
use App\Models\Permission;

class DeletePermissionAction
{
    /**
     * @throws ProtectedRecordCannotBeChanged
     * @throws PermissionIsInUse
     */
    public function handle(Permission $permission): void
    {
        $permission->delete();
    }
}
