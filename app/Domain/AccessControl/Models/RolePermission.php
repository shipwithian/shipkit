<?php

namespace App\Domain\AccessControl\Models;

use App\Domain\AccessControl\Exceptions\ProtectedRecordCannotBeChanged;
use Illuminate\Database\Eloquent\Relations\Pivot;
use ShipKit\Core\Eloquent\GuardsPivotInvariants;
use Spatie\Permission\PermissionRegistrar;

/**
 * The pivot row that gives a role a permission.
 *
 * `Role::permissions()` and `Permission::roles()` attach through this model, so every detach,
 * `revokePermissionTo()`, and `syncPermissions()` runs the guard below.
 */
class RolePermission extends Pivot
{
    use GuardsPivotInvariants;

    public function getTable(): string
    {
        return (string) config('permission.table_names.role_has_permissions');
    }

    /**
     * @throws ProtectedRecordCannotBeChanged
     */
    public function ensureCanBeDeleted(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $role = Role::query()->whereKey($this->getAttribute($registrar->pivotRole))->first();
        $permission = Permission::query()->whereKey($this->getAttribute($registrar->pivotPermission))->first();

        if ($role?->is_protected && $permission?->is_protected) {
            throw ProtectedRecordCannotBeChanged::protectedPermissionRemoval();
        }
    }

    protected function guardDelete(): void
    {
        $this->ensureCanBeDeleted();
    }
}
