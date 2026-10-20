<?php

namespace App\Http\Props\Authentication;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `auth/forgot-password`.
 */
#[TypeScript]
final readonly class ForgotPasswordProps extends PageProps
{
    public function __construct(
        public ?string $status,
    ) {}
}
