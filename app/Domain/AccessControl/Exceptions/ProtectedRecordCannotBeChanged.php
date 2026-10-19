<?php

namespace App\Domain\AccessControl\Exceptions;

use ShipKit\Core\Exceptions\DomainException;

class ProtectedRecordCannotBeChanged extends DomainException
{
    public function __construct(string $message, private readonly string $field)
    {
        parent::__construct($message);
    }

    public static function roleRename(): self
    {
        return new self(__('Protected roles cannot be renamed.'), 'name');
    }

    public static function roleDeletion(): self
    {
        return new self(__('Protected roles cannot be deleted.'), 'role');
    }

    public static function protectedPermissionRemoval(): self
    {
        return new self(__('Protected roles must keep their protected permissions.'), 'permissions');
    }

    public static function permissionRename(): self
    {
        return new self(__('Protected permissions cannot be renamed.'), 'name');
    }

    public static function permissionDeletion(): self
    {
        return new self(__('Protected permissions cannot be deleted.'), 'permission');
    }

    public static function protectionMarker(): self
    {
        return new self(__('Protection is set when a system record is created and can never change.'), 'is_protected');
    }

    public function field(): string
    {
        return $this->field;
    }
}
