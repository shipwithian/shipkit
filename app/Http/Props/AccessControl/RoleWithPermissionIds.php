<?php

namespace App\Http\Props\AccessControl;

use App\Domain\AccessControl\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class RoleWithPermissionIds
{
    /**
     * @param  list<int>  $permission_ids
     */
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_protected,
        public array $permission_ids,
    ) {}

    /**
     * Map a role whose permissions are already loaded.
     */
    public static function from(Role $role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            is_protected: $role->is_protected,
            permission_ids: array_values(array_map(intval(...), $role->permissions->modelKeys())),
        );
    }
}
