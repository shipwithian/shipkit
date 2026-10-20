<?php

namespace App\Http\Props\AccessControl;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the actor may do to one role, from the `RolePolicy` abilities its controllers authorize.
 */
#[TypeScript]
final readonly class RoleAbilities
{
    public function __construct(
        public bool $update,
        public bool $delete,
        public bool $assignPermissions,
    ) {}
}
