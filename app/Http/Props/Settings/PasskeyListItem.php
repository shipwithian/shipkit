<?php

namespace App\Http\Props\Settings;

use Laravel\Passkeys\Passkey;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class PasskeyListItem
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $authenticator,
        public ?string $created_at_diff,
        public ?string $last_used_at_diff,
    ) {}

    public static function from(Passkey $passkey): self
    {
        return new self(
            id: $passkey->id,
            name: $passkey->name,
            authenticator: $passkey->authenticator,
            created_at_diff: $passkey->created_at?->diffForHumans(),
            last_used_at_diff: $passkey->last_used_at?->diffForHumans(),
        );
    }
}
