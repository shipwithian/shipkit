<?php

namespace App\Domain\Authentication\Actions;

use Laravel\Sanctum\PersonalAccessToken;

class RevokeApiTokenAction
{
    public function handle(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
