<?php

namespace App\Domain\AccessControl\Exceptions;

use ShipKit\Core\Exceptions\DomainException;

class PermissionIsInUse extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('Remove this permission from all roles and users before deleting it.'));
    }

    public function field(): string
    {
        return 'permission';
    }
}
