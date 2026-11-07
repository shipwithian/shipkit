<?php

namespace App\Actions\AccessControl;

use App\Exceptions\ProtectedRecordCannotBeChanged;
use App\Exceptions\RoleIsInUse;
use App\Models\Role;

class DeleteRoleAction
{
    /**
     * @throws ProtectedRecordCannotBeChanged
     * @throws RoleIsInUse
     */
    public function handle(Role $role): void
    {
        $role->delete();
    }
}
