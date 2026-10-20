<?php

namespace App\Http\Props\AccessControl;

use App\Domain\AccessControl\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class RoleListItem
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_protected,
        public int $permissions_count,
        public int $users_count,
        public RoleAbilities $can,
    ) {}

    /**
     * Map a role loaded by `RoleListQuery`, with its permission and user counts, and what the actor may do to it.
     */
    public static function from(Role $role, RoleAbilities $can): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            is_protected: $role->is_protected,
            permissions_count: $role->permissions_count,
            users_count: $role->users_count,
            can: $can,
        );
    }
}
