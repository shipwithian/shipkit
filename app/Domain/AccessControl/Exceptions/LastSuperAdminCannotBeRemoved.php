<?php

namespace App\Domain\AccessControl\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class LastSuperAdminCannotBeRemoved extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('The last super admin cannot lose that role or be deleted.'));
    }

    public function field(): string
    {
        return 'roles';
    }
}
