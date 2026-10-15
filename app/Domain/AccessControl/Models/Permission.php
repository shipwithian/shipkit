<?php

namespace App\Domain\AccessControl\Models;

use App\Domain\AccessControl\Exceptions\PermissionIsInUse;
use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\Shared\Eloquent\GuardsBulkWrites;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property bool $is_protected
 * @property int $roles_count
 * @property int $users_count
 */
class Permission extends SpatiePermission
{
    use GuardsBulkWrites;

    /**
     * Guard the aggregate's invariants on every save and delete.
     *
     * Registered while booting so they run before Spatie's own model events.
     */
    protected static function booting(): void
    {
        static::updating(fn (self $permission) => $permission->ensureCanBeRenamedTo($permission->name));
        static::deleting(fn (self $permission) => $permission->ensureCanBeDeleted());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
        ];
    }

    /**
     * @throws ProtectedRecordCannotBeChanged
     */
    public function ensureCanBeRenamedTo(string $name): void
    {
        if ($this->getOriginal('is_protected') && $this->getOriginal('name') !== $name) {
            throw ProtectedRecordCannotBeChanged::permissionRename();
        }
    }

    /**
     * @throws ProtectedRecordCannotBeChanged
     * @throws PermissionIsInUse
     */
    public function ensureCanBeDeleted(): void
    {
        if ($this->getOriginal('is_protected')) {
            throw ProtectedRecordCannotBeChanged::permissionDeletion();
        }

        if ($this->roles()->exists() || $this->users()->exists()) {
            throw new PermissionIsInUse;
        }
    }
}
