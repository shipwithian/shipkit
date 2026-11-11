<?php

namespace App\Http\Props\Settings;

use App\Models\OAuthClient;
use Illuminate\Support\Carbon;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ConnectedAppListItem
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $approved_at_diff,
        public ?string $last_used_at_diff,
    ) {}

    /**
     * Map a client from ConnectedAppListQuery, which adds `approved_at` and `last_used_at`.
     */
    public static function from(OAuthClient $client): self
    {
        $approvedAt = $client->getAttribute('approved_at');
        $lastUsedAt = $client->getAttribute('last_used_at');

        return new self(
            id: (string) $client->getKey(),
            name: $client->name,
            approved_at_diff: is_string($approvedAt) ? Carbon::parse($approvedAt)->diffForHumans() : null,
            last_used_at_diff: is_string($lastUsedAt) ? Carbon::parse($lastUsedAt)->diffForHumans() : null,
        );
    }
}
