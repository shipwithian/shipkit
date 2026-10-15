<?php

namespace App\Domain\Authentication\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenListQuery
{
    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function handle(User $user): Collection
    {
        /** @var Collection<int, PersonalAccessToken> */
        return $user->tokens()
            ->select(['id', 'tokenable_type', 'tokenable_id', 'name', 'created_at', 'last_used_at', 'expires_at'])
            ->latest()
            ->latest('id')
            ->get();
    }
}
