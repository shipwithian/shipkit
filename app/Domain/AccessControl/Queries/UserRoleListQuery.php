<?php

namespace App\Domain\AccessControl\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRoleListQuery
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();
    }
}
