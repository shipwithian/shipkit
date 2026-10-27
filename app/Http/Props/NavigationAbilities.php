<?php

namespace App\Http\Props;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which access control pages the signed-in user may open, from the policy abilities their index
 * controllers authorize. Every ability is false for a guest.
 */
#[TypeScript]
final readonly class NavigationAbilities
{
    public function __construct(
        public bool $viewRoles,
        public bool $viewPermissions,
        public bool $viewUsers,
    ) {}
}
