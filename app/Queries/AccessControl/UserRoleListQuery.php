<?php

namespace App\Queries\AccessControl;

use App\Concerns\SearchesText;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRoleListQuery
{
    use SearchesText;

    /**
     * Users whose name or email contains the search, holding the role when one is given.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(?string $search = null, ?int $roleId = null): LengthAwarePaginator
    {
        return $this->whereContains(User::query(), ['name', 'email'], $search)
            ->when($roleId !== null, fn ($users) => $users->whereHas('roles', fn ($roles) => $roles->whereKey($roleId)))
            ->with('roles:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();
    }
}
