<?php

namespace App\Http\Props\Settings;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `settings/security`. The two-factor flags are false when two-factor management is disabled.
 * A newly created personal access token's secret is not a prop: it arrives once as the `personalAccessToken` flash.
 */
#[TypeScript]
final readonly class SecurityEditProps extends PageProps
{
    /**
     * @param  list<PasskeyListItem>  $passkeys
     * @param  list<PersonalAccessTokenListItem>  $personalAccessTokens
     * @param  list<ConnectedAppListItem>  $connectedApps
     */
    public function __construct(
        public bool $canManageTwoFactor,
        public bool $twoFactorEnabled,
        public bool $requiresConfirmation,
        public bool $canManagePasskeys,
        public array $passkeys,
        public string $passwordRules,
        public array $personalAccessTokens,
        public array $connectedApps,
    ) {}
}
