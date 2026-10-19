<?php

namespace ShipKit\Core\AccessControl;

use ShipKit\Core\Exceptions\DomainException;

/**
 * A role, permission, or user with a system name exists but is not protected.
 *
 * The marker can never change after creation, so bootstrap cannot repair it; a reviewed data
 * migration must.
 */
class SystemRecordIsNotProtected extends DomainException
{
    public static function for(string $kind, string $name): self
    {
        return new self("The {$kind} [{$name}] already exists but is not protected, and protection cannot be added to an existing record. Write a data migration that resolves it before bootstrapping access control.");
    }

    public function field(): string
    {
        return 'access_control';
    }
}
