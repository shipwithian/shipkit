<?php

namespace App\Http\Props\AccessControl;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `permissions/index`.
 */
#[TypeScript]
final readonly class PermissionIndexProps extends PageProps
{
    /**
     * @param  list<PermissionListItem>  $permissions
     */
    public function __construct(
        public array $permissions,
    ) {}
}
