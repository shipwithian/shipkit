<?php

namespace App\Actions\Api;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;

class VerifyApiEmailAction
{
    /**
     * @throws AuthorizationException
     */
    public function handle(int $userId, string $hash): User
    {
        $user = User::query()->findOrFail($userId);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AuthorizationException;
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $user;
    }
}
