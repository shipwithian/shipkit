<?php

use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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

    $this->withToken($tokens['access_token'])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk();

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

    $this->withToken($token)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk();
});

it('rate-limits client registration', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/oauth/register', ['client_name' => "Bot {$attempt}", 'redirect_uris' => ['https://bot.test/cb']])->assertCreated();
    }

    $this->postJson('/oauth/register', ['client_name' => 'Bot 11', 'redirect_uris' => ['https://bot.test/cb']])->assertTooManyRequests();

    $this->getJson('/.well-known/oauth-authorization-server')->assertOk();
});
