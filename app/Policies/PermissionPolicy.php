<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use ShipKit\Core\AccessControl\SystemPermission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(SystemPermission::ManagePermissionsResource->value);
    }

    public function create(User $user): bool
    {
        return $user->can(SystemPermission::ManagePermissionsResource->value);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->can(SystemPermission::ManagePermissionsResource->value);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->can(SystemPermission::ManagePermissionsResource->value);
    }
}
