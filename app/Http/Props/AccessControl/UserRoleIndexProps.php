<?php

namespace App\Http\Props\AccessControl;

use App\Http\Props\PageProps;
use App\Http\Props\Pagination;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `users/index`.
 */
#[TypeScript]
final readonly class UserRoleIndexProps extends PageProps
{
    /**
     * @param  list<UserRoleListItem>  $users
     * @param  list<AssignableRole>  $roles
     */
    public function __construct(
        public array $users,
        public Pagination $pagination,
        public array $roles,
    ) {}
}
