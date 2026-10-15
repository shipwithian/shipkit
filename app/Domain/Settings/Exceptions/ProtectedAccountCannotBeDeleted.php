<?php

namespace App\Domain\Settings\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class ProtectedAccountCannotBeDeleted extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('Protected accounts cannot be deleted.'));
    }

    public function field(): string
    {
        return 'user';
    }
}
