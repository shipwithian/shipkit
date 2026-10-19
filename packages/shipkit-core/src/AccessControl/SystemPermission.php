<?php

namespace ShipKit\Core\AccessControl;

enum SystemPermission: string
{
    case ManageRolesResource = 'manage roles resource';
    case ManagePermissionsResource = 'manage permissions resource';
    case AssignPermissions = 'assign permissions';
}
