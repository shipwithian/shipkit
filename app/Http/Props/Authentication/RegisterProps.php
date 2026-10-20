<?php

namespace App\Http\Props\Authentication;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `auth/register`.
 */
#[TypeScript]
final readonly class RegisterProps extends PageProps
{
    public function __construct(
        public string $passwordRules,
    ) {}
}
