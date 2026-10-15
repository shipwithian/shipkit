<?php

namespace App\Domain\AccessControl\Actions;

use App\Domain\AccessControl\Models\Permission;

class CreatePermissionAction
{
    public function handle(string $name): Permission
    {
        return Permission::query()->create(['name' => $name, 'guard_name' => 'web']);
    }
}
