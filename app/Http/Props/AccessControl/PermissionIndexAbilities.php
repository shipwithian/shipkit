<?php

namespace App\Http\Props\AccessControl;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the actor may do on `permissions/index` beyond one row, from `PermissionPolicy`.
 */
#[TypeScript]
final readonly class PermissionIndexAbilities
{
    public function __construct(
        public bool $create,
    ) {}
}
