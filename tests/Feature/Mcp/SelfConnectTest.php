<?php

use App\Actions\Auth\LimitClientToMcpAction;
use App\Http\Middleware\LimitRegisteredClientsToMcp;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;

/**
 * Register an AI client the way Claude or ChatGPT does: by itself, through the public endpoint.
 */
function registerAiClient(string $name = 'Claude'): OAuthClient
{
    $clientId = test()->postJson('/oauth/register', [
        'client_name' => $name,
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertCreated()->json('client_id');

    return OAuthClient::query()->findOrFail($clientId);
}

it('publishes the discovery metadata an AI client needs', function () {
    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp'));

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
        ->assertJsonPath('token_endpoint', url('/oauth/token'))
        ->assertJsonPath('registration_endpoint', url('/oauth/register'))
        ->assertJson(['code_challenge_methods_supported' => ['S256']]);
});

it('connects a self-registered AI client that reaches /mcp but not the REST API', function () {
    $user = User::factory()->create();
    $claude = registerAiClient();

    expect($claude->scopes)->toBe(['mcp:use'])
        ->and($claude->confidential())->toBeFalse();

    $tokens = tokensThroughConsent($user, $claude, 'mcp:use api', 'https://claude.ai/api/mcp/auth_callback');

    Auth::forgetGuards();

    mcpRequest($tokens['access_token'], 'tools/list')->assertOk();

    Auth::forgetGuards();

    $this->withToken($tokens['access_token'])->getJson('/api/v1/user')->assertForbidden();
});

it('lists a self-registered AI client under connected apps only after the user approves it', function () {
    $user = User::factory()->create();
    $claude = registerAiClient('Claude');

    $connectedApps = fn () => $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->inertiaProps('connectedApps');

    expect($connectedApps())->toBe([]);

    tokensThroughConsent($user, $claude, 'mcp:use', 'https://claude.ai/api/mcp/auth_callback');

    expect(collect($connectedApps())->pluck('name')->all())->toBe(['Claude']);
});

it('lets a personal access token use /mcp', function () {
    $token = User::factory()->create()->createToken('Script', ['api', 'mcp:use'])->accessToken;

    mcpRequest($token, 'tools/list')->assertOk();
});

it('rate-limits client registration', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/oauth/register', ['client_name' => "Bot {$attempt}", 'redirect_uris' => ['https://claude.ai/cb']])->assertCreated();
    }

    $this->postJson('/oauth/register', ['client_name' => 'Bot 11', 'redirect_uris' => ['https://claude.ai/cb']])->assertTooManyRequests();

    $this->getJson('/.well-known/oauth-authorization-server')->assertOk();
});

it('refuses to register a client that redirects to a site that is not allowed', function () {
    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://evil.test/callback']])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_redirect_uri');

    expect(OAuthClient::query()->where('name', 'Claude')->exists())->toBeFalse();
});

it('registers clients that redirect to claude.ai, chatgpt.com, or localhost on any port', function (string $redirect) {
    $this->postJson('/oauth/register', ['client_name' => 'AI client', 'redirect_uris' => [$redirect]])->assertCreated();
})->with([
    'https://claude.ai/api/mcp/auth_callback',
    'https://chatgpt.com/connector_platform_oauth_redirect',
    'http://localhost:33418/callback',
    'http://127.0.0.1:8123/callback',
]);

it('allows another site once it is added to the redirect domains', function () {
    $register = fn () => $this->postJson('/oauth/register', ['client_name' => 'Acme', 'redirect_uris' => ['https://app.acme.test/callback']]);

    $register()->assertBadRequest();

    config(['mcp.redirect_domains' => [...config('mcp.redirect_domains'), 'https://app.acme.test']]);

    $register()->assertCreated();
});

it('does not create a client when limiting it to mcp:use fails', function () {
    $this->mock(LimitClientToMcpAction::class)->shouldReceive('handle')->andThrow(new RuntimeException('could not limit'));

    $before = OAuthClient::query()->count();

    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/cb']])->assertServerError();

    expect(OAuthClient::query()->count())->toBe($before);
});

it('does not keep a client when registration answers with an error', function () {
    $before = OAuthClient::query()->count();

    $response = app(LimitRegisteredClientsToMcp::class)->handle(
        Request::create('/oauth/register', 'POST'),
        function () {
            registerAiClient();

            return new JsonResponse(['error' => 'server_error'], 500);
        },
    );

    expect($response->getStatusCode())->toBe(500)
        ->and(OAuthClient::query()->count())->toBe($before);
});

it('does not keep a client when a successful registration has no client id', function () {
    $before = OAuthClient::query()->count();

    expect(fn () => app(LimitRegisteredClientsToMcp::class)->handle(
        Request::create('/oauth/register', 'POST'),
        function () {
            registerAiClient();

            return new JsonResponse(['registered' => true], 201);
        },
    ))->toThrow(RuntimeException::class, 'without a client id');

    expect(OAuthClient::query()->count())->toBe($before);
});

it('ignores a wildcard in MCP_REDIRECT_DOMAINS, so no site can register as a trusted client', function () {
    // A real environment variable wins over .env, so this holds whatever .env defines.
    $result = Process::path(base_path())
        ->env(['MCP_REDIRECT_DOMAINS' => '*, https://app.acme.test'])
        ->run([PHP_BINARY, 'artisan', 'tinker', '--execute', 'echo json_encode(config("mcp.redirect_domains"));'])
        ->throw();

    expect(json_decode(trim($result->output()), true))->toBe(['https://claude.ai', 'https://chatgpt.com', 'http://localhost', 'https://app.acme.test']);
});
