<?php

namespace App\Http\Props\Authentication;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `auth/login`.
 */
#[TypeScript]
final readonly class LoginProps extends PageProps
{
    public function __construct(
        public bool $canResetPassword,
        public ?string $status,
    ) {}
}
