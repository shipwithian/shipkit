<?php

namespace App\Http\Props\Settings;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `settings/profile`.
 */
#[TypeScript]
final readonly class ProfileEditProps extends PageProps
{
    public function __construct(
        public bool $mustVerifyEmail,
        public ?string $status,
    ) {}
}
