<?php

namespace App\Providers;

use App\Enums\ApiScope;
use App\Http\Props\Auth\OAuthAuthorizeProps;
use App\Http\Props\Auth\OAuthScopeItem;
use App\Models\OAuthClient;
use App\Models\OAuthToken;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;
use LogicException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Configure the OAuth server: which grants exist, which scopes a token may carry, how long tokens
 * live, and the consent screen.
 */
class PassportServiceProvider extends ServiceProvider
{
    /**
     * Turn the device grant off before Passport registers its routes, so its routes never exist.
     */
    public function register(): void
    {
        Passport::$deviceCodeGrantEnabled = false;
    }

    public function boot(): void
    {
        Passport::useClientModel(OAuthClient::class);
        Passport::useTokenModel(OAuthToken::class);

        Passport::tokensCan(ApiScope::descriptions());

        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
        Passport::personalAccessTokensExpireIn(CarbonInterval::days((int) config('passport.personal_access_token_lifetime_days')));

        Passport::authorizationView(fn (array $parameters): SymfonyResponse => $this->consentScreen($parameters));
    }

    /**
     * @param  array<string, mixed>  $parameters  Passport's `request`, `authToken`, `client`, `user`, and `scopes`
     */
    private function consentScreen(array $parameters): SymfonyResponse
    {
        ['request' => $request, 'authToken' => $authToken, 'client' => $client, 'user' => $user, 'scopes' => $scopes] = $parameters;

        if (! $request instanceof Request || ! is_string($authToken) || ! $client instanceof OAuthClient || ! $user instanceof User || ! is_array($scopes)) {
            throw new LogicException('Passport passed the consent screen unexpected parameters.');
        }

        return Inertia::render('auth/oauth-authorize', new OAuthAuthorizeProps(
            clientName: $client->name,
            scopes: array_values(array_map(OAuthScopeItem::from(...), array_filter($scopes, fn (mixed $scope): bool => $scope instanceof Scope))),
            accountName: $user->name,
            accountEmail: $user->email,
            clientId: (string) $client->getKey(),
            state: $request->string('state')->toString(),
            authToken: $authToken,
            csrfToken: csrf_token(),
            approveUrl: route('passport.authorizations.approve'),
            denyUrl: route('passport.authorizations.deny'),
        ))->toResponse($request);
    }
}
