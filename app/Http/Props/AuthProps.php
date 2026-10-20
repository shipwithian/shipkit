<?php

namespace App\Http\Props;

use App\Models\User;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The signed-in user, or null for a guest, and what they may open. The UI gates on `can`, which the
 * server computes from policies; permission names are never sent.
 */
#[TypeScript]
final readonly class AuthProps
{
    public function __construct(
        public ?AuthUser $user,
        public NavigationAbilities $can,
    ) {}

    public static function for(?User $user, NavigationAbilities $can): self
    {
        return new self(
            user: $user instanceof User ? AuthUser::from($user) : null,
            can: $can,
        );
    }
}
