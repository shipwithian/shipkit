<?php

namespace App\Queries\AccessControl;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class PermissionListQuery
{
    /**
     * @return Collection<int, Permission>
     */
    public function handle(): Collection
    {
        return Permission::query()
            ->withCount(['roles', 'users'])
            ->orderBy('name')
            ->get();
    }
}
