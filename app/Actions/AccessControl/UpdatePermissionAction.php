<?php

namespace App\Actions\AccessControl;

use App\Models\Permission;
use Illuminate\Validation\ValidationException;

class UpdatePermissionAction
{
    public function handle(Permission $permission, string $name): Permission
    {
        if ($permission->is_protected && $permission->name !== $name) {
            throw ValidationException::withMessages([
                'name' => __('Protected permissions cannot be renamed.'),
            ]);
        }

        $permission->update(['name' => $name]);

        return $permission;
    }
}
