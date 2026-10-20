<?php

namespace App\Http\Props;

use App\Models\User;
use Spatie\Permission\Contracts\Permission;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The signed-in user and the permission names they hold, or a null user and no permissions for a guest.
 */
#[TypeScript]
final readonly class AuthProps
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public ?AuthUser $user,
        public array $permissions,
    ) {}

    public static function for(?User $user): self
    {
        if (! $user instanceof User) {
            return new self(user: null, permissions: []);
        }

        return new self(
            user: AuthUser::from($user),
            permissions: array_values($user->getAllPermissions()
                ->map(fn (Permission $permission): string => $permission->name)
                ->sort()
                ->all()),
        );
    }
}
