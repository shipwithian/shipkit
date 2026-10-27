<?php

namespace App\Http\Props\AccessControl;

use App\Models\User;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class UserRoleListItem
{
    /**
     * @param  list<int>  $role_ids
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $role_ids,
    ) {}

    /**
     * Map a user loaded by `UserRoleListQuery`, with its roles.
     */
    public static function from(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role_ids: array_values(array_map(intval(...), $user->roles->modelKeys())),
        );
    }
}
