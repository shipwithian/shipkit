<?php

namespace App\Policies;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(SystemPermission::ManageRolesResource->value)
            || $user->can(SystemPermission::AssignPermissions->value);
    }

    public function create(User $user): bool
    {
        return $user->can(SystemPermission::ManageRolesResource->value);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(SystemPermission::ManageRolesResource->value);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(SystemPermission::ManageRolesResource->value);
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return $user->can(SystemPermission::AssignPermissions->value);
    }
}
