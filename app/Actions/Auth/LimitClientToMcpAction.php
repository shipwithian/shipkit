<?php

namespace App\Actions\Auth;

use App\Enums\ApiScope;
use App\Models\OAuthClient;

class LimitClientToMcpAction
{
    /**
     * Allow a client only the `mcp:use` scope. Passport drops any other scope it asks for.
     */
    public function handle(string $clientId): void
    {
        OAuthClient::query()->whereKey($clientId)->firstOrFail()
            ->forceFill(['scopes' => [ApiScope::McpUse->value]])
            ->save();
    }
}
