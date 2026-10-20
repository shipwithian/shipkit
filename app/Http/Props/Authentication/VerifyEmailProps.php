<?php

namespace App\Http\Props\Authentication;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `auth/verify-email`.
 */
#[TypeScript]
final readonly class VerifyEmailProps extends PageProps
{
    public function __construct(
        public ?string $status,
    ) {}
}
