<?php

namespace App\Http\Props\AccessControl;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `roles/permissions`.
 */
#[TypeScript]
final readonly class RolePermissionsEditProps extends PageProps
{
    /**
     * @param  list<AssignablePermission>  $permissions
     */
    public function __construct(
        public RoleWithPermissionIds $role,
        public array $permissions,
    ) {}
}
