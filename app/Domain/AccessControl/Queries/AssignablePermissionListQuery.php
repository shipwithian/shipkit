<?php

namespace App\Domain\AccessControl\Queries;

use App\Domain\AccessControl\Models\Permission;
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
