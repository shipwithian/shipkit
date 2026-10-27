<?php

namespace App\Domain\AccessControl\Exceptions;

use ShipKit\Core\Exceptions\DomainException;

class RoleExceedsActorPermissions extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('You can only assign or remove roles whose permissions you hold.'));
    }

    public function field(): string
    {
        return 'roles';
    }
}
