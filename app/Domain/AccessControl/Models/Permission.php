<?php

namespace App\Domain\AccessControl\Models;

use App\Domain\AccessControl\Exceptions\PermissionIsInUse;
use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ShipKit\Core\Eloquent\GuardsInvariants;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property bool $is_protected
 * @property int $roles_count
 * @property int $users_count
 */
class Permission extends SpatiePermission
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
     * The roles holding this permission, attached through {@see RolePermission} so removals are guarded.
     *
     * @return BelongsToMany<Model, Model, RolePermission, 'pivot'>
     */
    public function roles(): BelongsToMany
    {
        return parent::roles()->using(RolePermission::class);
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

    protected function guardUpdate(): void
    {
        $this->ensureCanBeRenamedTo($this->name);
    }

    protected function guardDelete(): void
    {
        $this->ensureCanBeDeleted();
    }
}
