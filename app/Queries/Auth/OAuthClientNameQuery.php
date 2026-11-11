<?php

namespace App\Queries\Auth;

use App\Models\OAuthClient;

class OAuthClientNameQuery
{
    /**
     * The name an OAuth client gave itself, for logs. It is self-declared, so it never decides anything.
     */
    public function handle(string $clientId): ?string
    {
        $name = OAuthClient::query()->whereKey($clientId)->value('name');

        return is_string($name) ? $name : null;
    }
}
