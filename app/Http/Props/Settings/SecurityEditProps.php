<?php

namespace App\Http\Props\Settings;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `settings/security`. The two-factor flags are false when two-factor management is disabled.
 */
#[TypeScript]
final readonly class SecurityEditProps extends PageProps
{
    /**
     * @param  list<PasskeyListItem>  $passkeys
     */
    public function __construct(
        public bool $canManageTwoFactor,
        public bool $twoFactorEnabled,
        public bool $requiresConfirmation,
        public bool $canManagePasskeys,
        public array $passkeys,
        public string $passwordRules,
    ) {}
}
