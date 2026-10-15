<?php

namespace App\Domain\AccessControl\Actions;

use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Models\Permission;

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
