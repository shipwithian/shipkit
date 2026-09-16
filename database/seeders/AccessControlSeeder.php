<?php

namespace Database\Seeders;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $this->renameLegacyPermission('manage roles', SystemPermission::ManageRolesResource);
        $this->renameLegacyPermission('manage permissions', SystemPermission::ManagePermissionsResource);

        $permissions = collect(SystemPermission::cases())->map(function (SystemPermission $permission): Permission {
            /** @var Permission $model */
            $model = Permission::findOrCreate($permission, 'web');
            $model->forceFill(['is_protected' => true])->save();

            return $model;
        });

        /** @var Role $role */
        $role = Role::findOrCreate(SystemRole::SuperAdmin, 'web');
        $role->forceFill(['is_protected' => true])->save();
        $role->givePermissionTo($permissions);

        $user = User::query()->firstOrCreate(
            ['email' => $this->credential('email', 'admin@example.com')],
            [
                'name' => $this->credential('name', 'Super Admin'),
                'password' => $this->credential('password', 'password'),
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill(['is_protected' => true])->save();
        $user->assignRole($role);

        $registrar->forgetCachedPermissions();
    }

    private function renameLegacyPermission(string $legacyName, SystemPermission $replacement): void
    {
        $legacyPermission = Permission::query()
            ->where('name', $legacyName)
            ->where('guard_name', 'web')
            ->first();

        if (! $legacyPermission) {
            return;
        }

        $replacementPermission = Permission::query()
            ->where('name', $replacement->value)
            ->where('guard_name', 'web')
            ->first();

        if (! $replacementPermission) {
            $legacyPermission->forceFill(['name' => $replacement->value])->save();

            return;
        }

        foreach ($legacyPermission->roles as $role) {
            if ($role instanceof Role) {
                $role->givePermissionTo($replacementPermission);
            }
        }

        foreach ($legacyPermission->users as $user) {
            if ($user instanceof User) {
                $user->givePermissionTo($replacementPermission);
            }
        }

        $legacyPermission->delete();
    }

    private function credential(string $key, string $localDefault): string
    {
        $value = config("access_control.super_admin.{$key}");

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (app()->isProduction()) {
            throw new RuntimeException('SUPER_ADMIN_'.strtoupper($key).' must be configured in production.');
        }

        return $localDefault;
    }
}
