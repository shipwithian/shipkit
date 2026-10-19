<?php

namespace App\Domain\AccessControl\Models;

use App\Domain\AccessControl\Exceptions\LastSuperAdminCannotBeRemoved;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use ShipKit\Core\Eloquent\GuardsPivotInvariants;
use Spatie\Permission\PermissionRegistrar;

/**
 * The pivot row that gives a user a role.
 *
 * `User::roles()` and `Role::users()` attach through this model, so every detach, `removeRole()`,
 * and `syncRoles()` runs the guard below.
 */
class RoleAssignment extends MorphPivot
{
    use GuardsPivotInvariants;

    public function getTable(): string
    {
        return (string) config('permission.table_names.model_has_roles');
    }

    /**
     * @throws LastSuperAdminCannotBeRemoved
     */
    public function ensureCanBeDeleted(): void
    {
        if ($this->getAttribute('model_type') !== (new User)->getMorphClass()) {
            return;
        }

        Role::query()
            ->whereKey($this->getAttribute(app(PermissionRegistrar::class)->pivotRole))
            ->first()
            ?->ensureCanLoseHolder($this->getAttribute((string) config('permission.column_names.model_morph_key')));
    }

    protected function guardDelete(): void
    {
        $this->ensureCanBeDeleted();
    }
}
