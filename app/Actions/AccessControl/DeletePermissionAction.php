<?php

namespace App\Actions\AccessControl;

use App\Models\Permission;
use Illuminate\Validation\ValidationException;

class DeletePermissionAction
{
    public function handle(Permission $permission): void
    {
        if ($permission->is_protected) {
            throw ValidationException::withMessages([
                'permission' => __('Protected permissions cannot be deleted.'),
            ]);
        }

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            throw ValidationException::withMessages([
                'permission' => __('Remove this permission from all roles and users before deleting it.'),
            ]);
        }

        $permission->delete();
    }
}
