<?php

namespace App\Actions\Api;

use App\Actions\ApiTokens\CreateApiTokenAction;
use Laravel\Sanctum\NewAccessToken;

class LoginApiUserAction
{
    public function __construct(
        private readonly AuthenticateApiUserAction $authenticateApiUser,
        private readonly CreateApiTwoFactorChallengeAction $createApiTwoFactorChallenge,
        private readonly CreateApiTokenAction $createApiToken,
    ) {}

    /**
     * Issue a token, or a two factor challenge when the user has two factor authentication enabled.
     *
     * @return NewAccessToken|array{challenge_token: string, expires_at: string}
     */
    public function handle(string $username, string $password, string $deviceName): NewAccessToken|array
    {
        $user = $this->authenticateApiUser->handle($username, $password);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $this->createApiTwoFactorChallenge->handle($user, $deviceName);
        }

        return $this->createApiToken->handle($user, $deviceName);
    }
}
