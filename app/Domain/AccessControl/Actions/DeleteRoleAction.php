<?php

namespace App\Domain\AccessControl\Actions;

use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Exceptions\RoleIsInUse;
use App\Domain\AccessControl\Models\Role;

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
