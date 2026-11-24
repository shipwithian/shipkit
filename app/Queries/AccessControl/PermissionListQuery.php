<?php

namespace App\Queries\AccessControl;

use App\Concerns\SearchesText;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class PermissionListQuery
{
    use SearchesText;

    /**
     * Every permission, or the ones whose name contains the search.
     *
     * @return Collection<int, Permission>
     */
    public function handle(?string $search = null): Collection
    {
        return $this->whereContains(Permission::query(), ['name'], $search)
            ->withCount(['roles', 'users'])
            ->orderBy('name')
            ->get();
    }
}
