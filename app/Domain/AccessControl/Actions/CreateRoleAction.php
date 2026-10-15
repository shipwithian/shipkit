<?php

namespace App\Domain\AccessControl\Actions;

use App\Domain\AccessControl\Models\Role;

class CreateRoleAction
{
    public function handle(string $name): Role
    {
        return Role::query()->create(['name' => $name, 'guard_name' => 'web']);
    }
}
