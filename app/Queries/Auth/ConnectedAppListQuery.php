<?php

namespace App\Queries\Auth;

use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ConnectedAppListQuery
{
    /**
     * The apps the user has approved and not revoked: every client, other than the personal access
     * client, for which the user holds at least one non-revoked token. Each carries `approved_at`,
     * when the user first approved it, and `last_used_at`, newest first.
     *
     * @return Collection<int, OAuthClient>
     */
    public function handle(User $actor): Collection
    {
        return $this->connectedApps($actor)
            ->orderByDesc('connections.approved_at')
            ->get();
    }

    /**
     * @return Builder<OAuthClient>
     */
    public function connectedApps(User $actor): Builder
    {
        $connections = $actor->tokens()->getQuery()
            ->selectRaw('client_id, min(created_at) as approved_at, max(last_used_at) as last_used_at')
            ->where('revoked', false)
            ->whereNotIn('client_id', ApiTokenListQuery::personalAccessClientIds())
            ->groupBy('client_id');

        return OAuthClient::query()
            ->select(['oauth_clients.id', 'oauth_clients.name', 'connections.approved_at', 'connections.last_used_at'])
            ->joinSub($connections, 'connections', 'connections.client_id', '=', 'oauth_clients.id');
    }
}
