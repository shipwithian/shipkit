<?php

namespace App\Http\Props\Settings;

use App\Models\OAuthToken;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class PersonalAccessTokenListItem
{
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $created_at_diff,
        public ?string $last_used_at_diff,
        public ?string $expires_at_diff,
    ) {}

    public static function from(OAuthToken $token): self
    {
        return new self(
            id: (string) $token->getKey(),
            name: $token->name,
            created_at_diff: $token->created_at?->diffForHumans(),
            last_used_at_diff: $token->last_used_at?->diffForHumans(),
            expires_at_diff: $token->expires_at?->diffForHumans(),
        );
    }
}
