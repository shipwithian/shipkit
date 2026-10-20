<?php

namespace App\Http\Props\Authentication;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `auth/reset-password`.
 */
#[TypeScript]
final readonly class ResetPasswordProps extends PageProps
{
    public function __construct(
        public ?string $email,
        public string $token,
        public string $passwordRules,
    ) {}
}
