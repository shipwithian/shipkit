<?php

namespace App\Domain\AccessControl\Actions;

use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Models\Role;

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
