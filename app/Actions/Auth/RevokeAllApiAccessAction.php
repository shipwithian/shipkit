<?php

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Passport\Passport;

class RevokeAllApiAccessAction
{
    /**
     * Revoke every personal access token, every connected app's access and refresh tokens, and every
     * pending authorization code the user holds, so nothing stays connected after a password reset.
     */
    public function handle(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');

        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        Passport::token()->newQuery()->whereIn('id', $tokenIds)->update(['revoked' => true]);
        Passport::authCode()->newQuery()->where('user_id', $user->getKey())->update(['revoked' => true]);
    }
}
