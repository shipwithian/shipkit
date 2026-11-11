<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Laravel\Passport\Token;

/**
 * An access token: a personal access token, or one an app received through the consent screen.
 *
 * @property Carbon|null $last_used_at
 */
class OAuthToken extends Token
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'last_used_at' => 'datetime',
        ];
    }
}
