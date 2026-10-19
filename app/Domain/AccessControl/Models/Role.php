<?php

namespace App\Domain\AccessControl\Models;

use App\Domain\AccessControl\Exceptions\LastSuperAdminCannotBeRemoved;
use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use App\Domain\AccessControl\Exceptions\RoleIsInUse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ShipKit\Core\AccessControl\SystemRole;
use ShipKit\Core\Eloquent\GuardsInvariants;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property bool $is_protected
 * @property int $permissions_count
 * @property int $users_count
 */
class Role extends SpatieRole
{
    use GuardsInvariants;

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
     * The role's permissions, attached through {@see RolePermission} so removals are guarded.
     *
     * @return BelongsToMany<Model, Model, RolePermission, 'pivot'>
     */
    public function permissions(): BelongsToMany
    {
        return parent::permissions()->using(RolePermission::class);
    }

    /**
     * The users holding this role, attached through {@see RoleAssignment} so removals are guarded.
     *
     * @return BelongsToMany<Model, Model, RoleAssignment, 'pivot'>
     */
    public function users(): BelongsToMany
    {
        return parent::users()->using(RoleAssignment::class);
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

    /**
     * @throws ProtectedRecordCannotBeChanged
     */
    public function ensureProtectionIsUnchanged(): void
    {
        if ($this->isDirty('is_protected')) {
            throw ProtectedRecordCannotBeChanged::protectionMarker();
        }
    }

    protected function guardUpdate(): void
    {
        $this->ensureProtectionIsUnchanged();
        $this->ensureCanBeRenamedTo($this->name);
    }

    protected function guardDelete(): void
    {
        $this->ensureCanBeDeleted();
    }

    /**
     * Refuse to take this role from its only holder when it is the super-admin role.
     *
     * The holders are locked so two concurrent removals cannot both see another holder remaining;
     * the lock holds because {@see GuardsInvariants} runs the guard and the delete in one transaction.
     *
     * @throws LastSuperAdminCannotBeRemoved
     */
    public function ensureCanLoseHolder(int|string $holderId): void
    {
        if ($this->getOriginal('name') !== SystemRole::SuperAdmin->value) {
            return;
        }

        $holders = $this->users()->lockForUpdate()->limit(2)->get();

        if ($holders->count() === 1 && $holders->first()?->getKey() == $holderId) {
            throw new LastSuperAdminCannotBeRemoved;
        }
    }
}
