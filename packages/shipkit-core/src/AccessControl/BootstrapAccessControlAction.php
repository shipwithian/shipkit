<?php

namespace ShipKit\Core\AccessControl;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use ShipKit\Core\GuardedModelMap;
use Spatie\Permission\PermissionRegistrar;

/**
 * Create the protected records access control cannot recover without: the system permissions, the
 * super-admin role holding them, and the first super admin.
 *
 * The only code that writes `is_protected`, and only when it creates a record. It never changes an
 * existing record's protection, and stops when a system record exists unprotected.
 */
class BootstrapAccessControlAction
{
    private const string GUARD = 'web';

    public function __construct(
        private readonly GuardedModelMap $models,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @throws SystemRecordIsNotProtected
     */
    public function handle(string $superAdminName, string $superAdminEmail, string $superAdminPassword): void
    {
        $this->registrar->forgetCachedPermissions();

        DB::transaction(function () use ($superAdminName, $superAdminEmail, $superAdminPassword): void {
            $permissions = array_map(
                fn (SystemPermission $permission): Model => $this->protectedRecord($this->models->permission(), 'permission', 'name', $permission->value, [
                    'guard_name' => self::GUARD,
                ]),
                SystemPermission::cases(),
            );

            $role = $this->protectedRecord($this->models->role(), 'role', 'name', SystemRole::SuperAdmin->value, [
                'guard_name' => self::GUARD,
            ]);

            $user = $this->protectedRecord($this->models->user(), 'user', 'email', $superAdminEmail, [
                'name' => $superAdminName,
                'password' => $superAdminPassword,
                'email_verified_at' => now(),
            ]);

            if (! method_exists($role, 'givePermissionTo') || ! method_exists($user, 'assignRole')) {
                throw new LogicException('The mapped role and user models must use Spatie Permission\'s HasPermissions and HasRoles traits.');
            }

            $role->givePermissionTo($permissions);
            $user->assignRole($role);
        });

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * The existing protected record, or a new one created protected.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $attributes
     *
     * @throws SystemRecordIsNotProtected
     */
    private function protectedRecord(string $model, string $kind, string $column, string $value, array $attributes): Model
    {
        $query = $model::query()->where($column, $value);

        if ($kind !== 'user') {
            $query->where('guard_name', self::GUARD);
        }

        $existing = $query->first();

        if ($existing === null) {
            return $model::query()->forceCreate([$column => $value, ...$attributes, 'is_protected' => true]);
        }

        if (! $existing->getAttribute('is_protected')) {
            throw SystemRecordIsNotProtected::for($kind, $value);
        }

        return $existing;
    }
}
