<?php

namespace App\Domain\AccessControl\Enums;

enum SystemPermission: string
{
    case ManageRolesResource = 'manage roles resource';
    case ManagePermissionsResource = 'manage permissions resource';
    case AssignPermissions = 'assign permissions';
}
