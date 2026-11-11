<?php

namespace App\Actions\Auth;

use App\Models\OAuthClient;
use App\Models\User;
use Laravel\Passport\Passport;

class RevokeConnectedAppAction
{
    /**
     * Disconnect one app: revoke the actor's access and refresh tokens for that client, leaving the
     * actor's other apps and personal access tokens working.
     */
    public function handle(User $actor, OAuthClient $client): void
    {
        $tokenIds = $actor->tokens()->where('client_id', $client->getKey())->pluck('id');

        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        Passport::token()->newQuery()->whereIn('id', $tokenIds)->update(['revoked' => true]);
    }
}
