<?php

namespace App\Actions\AccessControl;

use App\Models\Role;
use Illuminate\Validation\ValidationException;

class UpdateRoleAction
{
    public function handle(Role $role, string $name): Role
    {
        if ($role->is_protected && $role->name !== $name) {
            throw ValidationException::withMessages([
                'name' => __('Protected roles cannot be renamed.'),
            ]);
        }

        $role->update(['name' => $name]);

        return $role;
    }
}
