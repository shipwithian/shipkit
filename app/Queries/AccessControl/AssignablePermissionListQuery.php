<?php

namespace App\Queries\AccessControl;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class AssignablePermissionListQuery
{
    /**
     * @return Collection<int, Permission>
     */
    public function handle(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get();
    }
}
