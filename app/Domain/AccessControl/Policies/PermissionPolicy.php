<?php

namespace App\Domain\AccessControl\Policies;

use App\Domain\AccessControl\Enums\SystemPermission;
use App\Domain\AccessControl\Models\Permission;
use App\Models\User;

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
