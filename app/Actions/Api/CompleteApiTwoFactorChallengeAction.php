<?php

namespace App\Actions\Api;

use App\Actions\ApiTokens\CreateApiTokenAction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\NewAccessToken;

class CompleteApiTwoFactorChallengeAction
{
    public function __construct(
        private readonly CreateApiTokenAction $createApiToken,
        private readonly CreateApiTwoFactorChallengeAction $createApiTwoFactorChallenge,
    ) {}

    public function handle(
        string $challengeToken,
        ?string $code,
        ?string $recoveryCode,
    ): NewAccessToken {
        return Cache::lock($this->createApiTwoFactorChallenge->cacheKey($challengeToken), 10)
            ->block(3, function () use ($challengeToken, $code, $recoveryCode): NewAccessToken {
                $challenge = Cache::get(
                    $this->createApiTwoFactorChallenge->cacheKey($challengeToken),
                );

                if (! is_array($challenge)) {
                    $this->throwInvalidChallenge();
                }

                $user = User::query()->find($challenge['user_id'] ?? null);

                if (! $user instanceof User || ! $user->hasEnabledTwoFactorAuthentication()) {
                    $this->throwInvalidChallenge();
                }

                if ($code !== null) {
                    $isValid = app(TwoFactorAuthenticationProvider::class)->verify(
                        Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                        $code,
                    );
                } elseif ($recoveryCode !== null) {
                    $isValid = collect($user->recoveryCodes())->contains(
                        fn (string $storedCode): bool => hash_equals($storedCode, $recoveryCode),
                    );
                } else {
                    $isValid = false;
                }

                if (! $isValid) {
                    TwoFactorAuthenticationFailed::dispatch($user);

                    throw ValidationException::withMessages([
                        'code' => [__('The provided two factor authentication code was invalid.')],
                    ]);
                }

                if ($recoveryCode !== null) {
                    $user->replaceRecoveryCode($recoveryCode);
                }

                Cache::forget($this->createApiTwoFactorChallenge->cacheKey($challengeToken));
                ValidTwoFactorAuthenticationCodeProvided::dispatch($user);

                return $this->createApiToken->handle(
                    $user,
                    (string) ($challenge['device_name'] ?? 'API client'),
                );
            });
    }

    private function throwInvalidChallenge(): never
    {
        throw ValidationException::withMessages([
            'challenge_token' => [__('The two factor authentication challenge is invalid or expired.')],
        ]);
    }
}
