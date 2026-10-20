<?php

namespace App\Http\Props;

use App\Models\User;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class AuthUser
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $email_verified_at,
        public bool $is_protected,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function from(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            email_verified_at: $user->email_verified_at?->toJSON(),
            is_protected: $user->is_protected,
            created_at: $user->created_at?->toJSON(),
            updated_at: $user->updated_at?->toJSON(),
        );
    }
}
