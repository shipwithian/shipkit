<?php

namespace App\Actions\AccessControl;

use App\Models\Role;

class CreateRoleAction
{
    public function handle(string $name): Role
    {
        return Role::query()->create(['name' => $name, 'guard_name' => 'web']);
    }
}
