<?php

namespace App\Http\Props\Auth;

use Laravel\Passport\Scope;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class OAuthScopeItem
{
    public function __construct(
        public string $id,
        public string $description,
    ) {}

    public static function from(Scope $scope): self
    {
        return new self(
            id: $scope->id,
            description: $scope->description,
        );
    }
}
