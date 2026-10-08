<?php

namespace App\Queries\AccessControl;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleListQuery
{
    /**
     * @return Collection<int, Role>
     */
    public function handle(): Collection
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();
    }
}
