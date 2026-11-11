<?php

namespace App\Actions\Auth;

use Laravel\Passport\Passport;

class RecordApiTokenUseAction
{
    /**
     * Mark a token as used now, at most once a minute so busy clients don't write on every request.
     */
    public function handle(string $tokenId): void
    {
        Passport::token()->newQuery()
            ->whereKey($tokenId)
            ->where(fn ($query) => $query->whereNull('last_used_at')->orWhere('last_used_at', '<', now()->subMinute()))
            ->update(['last_used_at' => now()]);
    }
}
