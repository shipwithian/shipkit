<?php

namespace App\Actions\Api;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

class CreateApiTwoFactorChallengeAction
{
    /**
     * @return array{challenge_token: string, expires_at: string}
     */
    public function handle(User $user, string $deviceName): array
    {
        $challengeToken = Str::random(64);
        $expiresAt = now()->addMinutes(5);

        Cache::put($this->cacheKey($challengeToken), [
            'device_name' => $deviceName,
            'user_id' => $user->getKey(),
        ], $expiresAt);

        TwoFactorAuthenticationChallenged::dispatch($user);

        return [
            'challenge_token' => $challengeToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function cacheKey(string $challengeToken): string
    {
        return 'api-two-factor:'.hash('sha256', $challengeToken);
    }
}
