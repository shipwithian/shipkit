<?php

namespace App\Domain\AccessControl\Exceptions;

use ShipKit\Core\Exceptions\DomainException;

class RoleIsInUse extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('Remove this role from all users before deleting it.'));
    }

    public function field(): string
    {
        return 'role';
    }
}
