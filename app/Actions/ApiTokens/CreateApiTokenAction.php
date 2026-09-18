<?php

namespace App\Actions\ApiTokens;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class CreateApiTokenAction
{
    public function handle(User $user, string $name): NewAccessToken
    {
        $expiration = config('sanctum.expiration');
        $expiresAt = $expiration === null
            ? null
            : now()->addMinutes((int) $expiration);

        return $user->createToken($name, ['*'], $expiresAt);
    }
}
