<?php

namespace App\Http\Props\AccessControl;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the actor may do to one permission, from the `PermissionPolicy` abilities its controller authorizes.
 */
#[TypeScript]
final readonly class PermissionAbilities
{
    public function __construct(
        public bool $update,
        public bool $delete,
    ) {}
}
