<?php

namespace App\Http\Props\AccessControl;

use App\Domain\AccessControl\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class AssignableRole
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    public static function from(Role $role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
        );
    }
}
