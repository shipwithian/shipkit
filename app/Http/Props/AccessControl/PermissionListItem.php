<?php

namespace App\Http\Props\AccessControl;

use App\Domain\AccessControl\Models\Permission;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class PermissionListItem
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_protected,
        public int $roles_count,
        public int $users_count,
        public PermissionAbilities $can,
    ) {}

    /**
     * Map a permission loaded by `PermissionListQuery`, with its role and user counts, and what the actor may do to it.
     */
    public static function from(Permission $permission, PermissionAbilities $can): self
    {
        return new self(
            id: $permission->id,
            name: $permission->name,
            is_protected: $permission->is_protected,
            roles_count: $permission->roles_count,
            users_count: $permission->users_count,
            can: $can,
        );
    }
}
