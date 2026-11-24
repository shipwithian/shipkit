<?php

namespace App\Queries\AccessControl;

use App\Concerns\SearchesText;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleListQuery
{
    use SearchesText;

    /**
     * Every role, or the ones whose name contains the search.
     *
     * @return Collection<int, Role>
     */
    public function handle(?string $search = null): Collection
    {
        return $this->whereContains(Role::query(), ['name'], $search)
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();
    }
}
