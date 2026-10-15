<?php

namespace App\Domain\Authentication\Queries;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenQuery
{
    /**
     * Find one of the user's own tokens, whichever device it was issued to.
     *
     * Another user's token is indistinguishable from a missing one.
     */
    public function handle(User $user, int $tokenId): PersonalAccessToken
    {
        /** @var PersonalAccessToken */
        return $user->tokens()->findOrFail($tokenId);
    }
}
