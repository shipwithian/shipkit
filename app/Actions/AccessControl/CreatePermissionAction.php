<?php

namespace App\Actions\AccessControl;

use App\Models\Permission;

class CreatePermissionAction
{
    public function handle(string $name): Permission
    {
        return Permission::query()->create(['name' => $name, 'guard_name' => 'web']);
    }
}
