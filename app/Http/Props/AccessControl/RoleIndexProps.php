<?php

namespace App\Http\Props\AccessControl;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `roles/index`.
 */
#[TypeScript]
final readonly class RoleIndexProps extends PageProps
{
    /**
     * @param  list<RoleListItem>  $roles
     */
    public function __construct(
        public array $roles,
    ) {}
}
