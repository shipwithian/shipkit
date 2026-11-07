<?php

namespace App\Actions\AccessControl;

use App\Exceptions\ProtectedRecordCannotBeChanged;
use App\Models\Role;

class UpdateRoleAction
{
    /**
     * @throws ProtectedRecordCannotBeChanged
     */
    public function handle(Role $role, string $name): Role
    {
        $role->update(['name' => $name]);

        return $role;
    }
}
