<?php

namespace App\Http\Props\Auth;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `auth/oauth-authorize`, the screen where a user approves or denies an app's request
 * for access. The approve and deny forms post `state`, `client_id`, and `auth_token` back to
 * Passport, as full-page submissions because Passport redirects to the app's own URL.
 */
#[TypeScript]
final readonly class OAuthAuthorizeProps extends PageProps
{
    /**
     * @param  list<OAuthScopeItem>  $scopes
     */
    public function __construct(
        public string $clientName,
        public array $scopes,
        public string $accountName,
        public string $accountEmail,
        public string $clientId,
        public string $state,
        public string $authToken,
        public string $csrfToken,
        public string $approveUrl,
        public string $denyUrl,
    ) {}
}
