<?php

namespace App\Queries\Auth;

use App\Models\OAuthClient;
use App\Models\User;

class ConnectedAppQuery
{
    public function __construct(private readonly ConnectedAppListQuery $connectedAppList) {}

    /**
     * Find one of the apps the actor has connected. An app the actor never approved, or another
     * user's app, is indistinguishable from a missing one.
     */
    public function handle(User $actor, string $clientId): OAuthClient
    {
        /** @var OAuthClient */
        return $this->connectedAppList->connectedApps($actor)
            ->where('oauth_clients.id', $clientId)
            ->firstOrFail();
    }
}
