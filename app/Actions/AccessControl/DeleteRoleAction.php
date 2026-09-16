<?php

namespace App\Actions\AccessControl;

use App\Models\Role;
use Illuminate\Validation\ValidationException;

class DeleteRoleAction
{
    public function handle(Role $role): void
    {
        if ($role->is_protected) {
            throw ValidationException::withMessages([
                'role' => __('Protected roles cannot be deleted.'),
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('Remove this role from all users before deleting it.'),
            ]);
        }

        $role->delete();
    }
}
