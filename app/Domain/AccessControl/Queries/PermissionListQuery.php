<?php

namespace App\Domain\AccessControl\Queries;

use App\Domain\AccessControl\Models\Permission;
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
