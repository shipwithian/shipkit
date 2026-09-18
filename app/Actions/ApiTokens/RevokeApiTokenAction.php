<?php

namespace App\Actions\ApiTokens;

use Laravel\Sanctum\PersonalAccessToken;

class RevokeApiTokenAction
{
    public function handle(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
