<?php

namespace App\Actions\Auth;

use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;

class RevokeApiTokenAction
{
    /**
     * Revoke one token, either a stored token or the token authenticating the current request.
     *
     * @param  Token|AccessToken<mixed>  $token
     */
    public function handle(Token|AccessToken $token): void
    {
        $token->revoke();
    }
}
