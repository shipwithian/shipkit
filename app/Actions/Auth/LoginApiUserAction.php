<?php

namespace App\Actions\Auth;

use Laravel\Passport\PersonalAccessTokenResult;

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
     * @return PersonalAccessTokenResult<mixed>|array{challenge_token: string, expires_at: string}
     */
    public function handle(string $username, string $password, string $deviceName): PersonalAccessTokenResult|array
    {
        $user = $this->authenticateApiUser->handle($username, $password);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $this->createApiTwoFactorChallenge->handle($user, $deviceName);
        }

        return $this->createApiToken->handle($user, $deviceName);
    }
}
