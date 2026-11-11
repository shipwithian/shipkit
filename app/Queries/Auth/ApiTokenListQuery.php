<?php

namespace App\Queries\Auth;

use App\Models\OAuthClient;
use App\Models\OAuthToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ApiTokenListQuery
{
    /**
     * The user's own personal access tokens that still work, newest first, whichever device each
     * was issued to. Tokens issued to connected apps are listed by ConnectedAppListQuery.
     *
     * @return Collection<int, OAuthToken>
     */
    public function handle(User $user): Collection
    {
        /** @var Collection<int, OAuthToken> */
        return $user->tokens()
            ->select(['id', 'user_id', 'client_id', 'name', 'created_at', 'last_used_at', 'expires_at'])
            ->whereIn('client_id', self::personalAccessClientIds())
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function personalAccessClientIds(): array
    {
        return array_values(OAuthClient::query()
            ->get(['id', 'grant_types'])
            ->filter(fn (OAuthClient $client): bool => $client->hasGrantType('personal_access'))
            ->map(fn (OAuthClient $client): string => (string) $client->getKey())
            ->all());
    }
}
