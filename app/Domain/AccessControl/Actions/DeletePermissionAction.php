<?php

namespace App\Domain\AccessControl\Actions;

use App\Domain\AccessControl\Exceptions\PermissionIsInUse;
use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Models\Permission;

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
