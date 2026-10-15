<?php

namespace App\Domain\AccessControl\Models;

use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Exceptions\RoleIsInUse;
use App\Domain\Shared\Eloquent\GuardsBulkWrites;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property bool $is_protected
 * @property int $permissions_count
 * @property int $users_count
 */
class Role extends SpatieRole
{
    use GuardsBulkWrites;

    /**
     * Guard the aggregate's invariants on every save and delete.
     *
     * Registered while booting so they run before Spatie's own model events.
     */
    protected static function booting(): void
    {
        static::updating(fn (self $role) => $role->ensureCanBeRenamedTo($role->name));
        static::deleting(fn (self $role) => $role->ensureCanBeDeleted());
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
            throw ProtectedRecordCannotBeChanged::roleRename();
        }
    }

    /**
     * @throws ProtectedRecordCannotBeChanged
     * @throws RoleIsInUse
     */
    public function ensureCanBeDeleted(): void
    {
        if ($this->getOriginal('is_protected')) {
            throw ProtectedRecordCannotBeChanged::roleDeletion();
        }

        if ($this->users()->exists()) {
            throw new RoleIsInUse;
        }
    }
}
