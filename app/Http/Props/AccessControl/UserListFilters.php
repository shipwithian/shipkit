<?php

namespace App\Http\Props\AccessControl;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The filters the users page was rendered with.
 */
#[TypeScript]
final readonly class UserListFilters
{
    public function __construct(
        public ?string $search,
        public ?int $role,
    ) {}
}
