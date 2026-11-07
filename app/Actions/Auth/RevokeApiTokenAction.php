<?php

namespace App\Actions\Auth;

use Laravel\Sanctum\PersonalAccessToken;

class RevokeApiTokenAction
{
    public function handle(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
