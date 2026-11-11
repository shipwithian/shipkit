<?php

use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Passport\ClientRepository;

/**
 * The query string an app sends to /oauth/authorize, with a PKCE challenge for the verifier.
 *
 * @return array<string, string>
 */
function authorizationRequest(OAuthClient $app, string $verifier): array
{
    return [
        'client_id' => (string) $app->getKey(),
        'redirect_uri' => 'https://partner.test/callback',
        'response_type' => 'code',
        'scope' => 'api',
        'state' => 'state-123',
        'code_challenge' => strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_'),
        'code_challenge_method' => 'S256',
    ];
}

/**
 * Open the consent screen as the user and approve it, returning the authorization code.
 */
function approveAuthorization(User $user, OAuthClient $app, string $verifier): string
{
    $authToken = null;

    test()->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query(authorizationRequest($app, $verifier)))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$authToken): void {
            $authToken = $page->toArray()['props']['authToken'];
        });

    $response = test()->post('/oauth/authorize', [
        'state' => 'state-123',
        'client_id' => (string) $app->getKey(),
        'auth_token' => $authToken,
    ]);

    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['state'] ?? null)->toBe('state-123');

    return (string) $query['code'];
}

/**
 * Exchange an authorization code for tokens, as the app does.
 */
function exchangeCode(OAuthClient $app, string $code, string $verifier): TestResponse
{
    return test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => (string) $app->getKey(),
        'redirect_uri' => 'https://partner.test/callback',
        'code_verifier' => $verifier,
        'code' => $code,
    ]);
}

describe('signing in', function () {
    it('sends a signed-out user to the login page first', function () {
        $this->get('/oauth/authorize?'.http_build_query(authorizationRequest(oauthApp(), Str::random(64))))
            ->assertRedirect(route('login'));
    });

    it('asks a user with two-factor authentication for their second factor', function () {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create();

        $this->get('/oauth/authorize?'.http_build_query(authorizationRequest(oauthApp(), Str::random(64))));

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    });
});

describe('consent', function () {
    it('shows the app, the requested scopes, and the account it will act as', function () {
        $user = User::factory()->create();
        $app = oauthApp('Calendar Sync');

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query(authorizationRequest($app, Str::random(64))))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/oauth-authorize')
                ->where('clientName', 'Calendar Sync')
                ->where('scopes.0.id', 'api')
                ->where('accountEmail', $user->email)
                ->where('redirectHost', 'partner.test')
                ->where('state', 'state-123')
                ->where('clientId', (string) $app->getKey())
                ->has('authToken')
                ->has('csrfToken'));
    });

    it('issues tokens that work on the API after the user approves', function () {
        $user = User::factory()->create();
        $app = oauthApp();
        $verifier = Str::random(64);

        $tokens = exchangeCode($app, approveAuthorization($user, $app, $verifier), $verifier)
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in'])
            ->json();

        expect($tokens['expires_in'])->toBeLessThanOrEqual(3600);

        Auth::forgetGuards();

        $this->withToken($tokens['access_token'])
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    });

    it('gives the app nothing when the user denies', function () {
        $user = User::factory()->create();
        $app = oauthApp();
        $authToken = null;

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query(authorizationRequest($app, Str::random(64))))
            ->assertInertia(function (Assert $page) use (&$authToken): void {
                $authToken = $page->toArray()['props']['authToken'];
            });

        $location = (string) $this->delete('/oauth/authorize', [
            'state' => 'state-123',
            'client_id' => (string) $app->getKey(),
            'auth_token' => $authToken,
        ])->headers->get('Location');

        expect($location)->toStartWith('https://partner.test/callback')
            ->toContain('error=access_denied')
            ->not->toContain('code=');
    });
});

describe('token exchange', function () {
    it('rejects a missing or wrong PKCE verifier', function () {
        $user = User::factory()->create();
        $app = oauthApp();
        $verifier = Str::random(64);
        $code = approveAuthorization($user, $app, $verifier);

        exchangeCode($app, $code, Str::random(64))->assertBadRequest();
        exchangeCode($app, $code, '')->assertBadRequest();
    });

    it('refreshes an access token', function () {
        $user = User::factory()->create();
        $app = oauthApp();
        $verifier = Str::random(64);
        $tokens = exchangeCode($app, approveAuthorization($user, $app, $verifier), $verifier)->json();

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => (string) $app->getKey(),
            'refresh_token' => $tokens['refresh_token'],
            'scope' => 'api',
        ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
    });

    it('refuses the password, client-credentials, and implicit grants', function () {
        $user = User::factory()->create();
        $app = oauthApp();

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id' => (string) $app->getKey(),
            'username' => $user->email,
            'password' => 'password',
            'scope' => 'api',
        ])->assertBadRequest()->assertJsonPath('error', 'unsupported_grant_type');

        $machine = app(ClientRepository::class)->createClientCredentialsGrantClient('Machine');

        $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => (string) $machine->getKey(),
            'client_secret' => $machine->plainSecret,
            'scope' => 'api',
        ])->assertBadRequest()->assertJsonPath('error', 'unauthorized_client');

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query([...authorizationRequest($app, Str::random(64)), 'response_type' => 'token']))
            ->assertBadRequest()
            ->assertJsonPath('error', 'unsupported_grant_type');
    });
});
