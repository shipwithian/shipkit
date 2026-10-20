<?php

namespace App\Http\Props\AccessControl;

use App\Domain\AccessControl\Models\Permission;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class AssignablePermission
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_protected,
    ) {}

    public static function from(Permission $permission): self
    {
        return new self(
            id: $permission->id,
            name: $permission->name,
            is_protected: $permission->is_protected,
        );
    }
}
