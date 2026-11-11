<?php

use App\Models\OAuthClient;
use Illuminate\Database\Migrations\Migration;
use Laravel\Passport\ClientRepository;

/**
 * Passport issues personal access tokens (API login, registration, two-factor completion, and
 * token creation) only through a personal access client, so every database gets one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = OAuthClient::query()
            ->get(['id', 'grant_types'])
            ->contains(fn (OAuthClient $client): bool => $client->hasGrantType('personal_access'));

        if (! $exists) {
            app(ClientRepository::class)->createPersonalAccessGrantClient(
                config('app.name').' Personal Access Client',
                'users',
            );
        }
    }

    public function down(): void
    {
        OAuthClient::query()
            ->get(['id', 'grant_types'])
            ->filter(fn (OAuthClient $client): bool => $client->hasGrantType('personal_access'))
            ->each(fn (OAuthClient $client): ?bool => $client->delete());
    }
};
