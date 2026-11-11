<?php

namespace App\Queries\Auth;

use App\Models\OAuthToken;
use App\Models\User;

class ApiTokenQuery
{
    /**
     * Find one of the user's own working personal access tokens, whichever device it was issued to.
     *
     * Another user's token is indistinguishable from a missing one.
     */
    public function handle(User $user, string $tokenId): OAuthToken
    {
        /** @var OAuthToken */
        return $user->tokens()
            ->whereIn('client_id', ApiTokenListQuery::personalAccessClientIds())
            ->where('revoked', false)
            ->findOrFail($tokenId);
    }
}
