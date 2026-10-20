<?php

namespace App\Http\Props\AccessControl;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the actor may do on `roles/index` beyond one row, from `RolePolicy`.
 */
#[TypeScript]
final readonly class RoleIndexAbilities
{
    public function __construct(
        public bool $create,
    ) {}
}
