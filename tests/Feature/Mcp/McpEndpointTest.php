<?php

use App\Http\Middleware\ShareChannel;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Testing\TestResponse;
use ShipKit\Core\Channel;

/**
 * Send one JSON-RPC message to /mcp with a bearer token.
 *
 * @param  array<string, mixed>  $params
 */
function mcpCall(?string $token, string $method, array $params = []): TestResponse
{
    $request = $token === null ? test() : test()->withToken($token);

    return $request->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
}

it('asks an unauthenticated client to sign in and says where', function () {
    mcpCall(null, 'tools/list')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
});

it('rejects a token without the mcp:use scope', function () {
    $token = User::factory()->create()->createToken('REST only', ['api'])->accessToken;

    mcpCall($token, 'tools/list')->assertForbidden();
});

it('lists the tools with their read-only and idempotent annotations', function () {
    $token = User::factory()->create()->createToken('Laptop', ['api', 'mcp:use'])->accessToken;

    $tools = collect(mcpCall($token, 'tools/list')->assertOk()->json('result.tools'))->keyBy('name');

    expect($tools->keys()->all())->toEqualCanonicalizing(['show_my_profile', 'update_my_profile', 'list_roles'])
        ->and($tools['show_my_profile']['annotations'])->toMatchArray(['readOnlyHint' => true, 'idempotentHint' => true])
        ->and($tools['list_roles']['annotations'])->toMatchArray(['readOnlyHint' => true])
        ->and($tools['update_my_profile']['annotations'])->toMatchArray(['idempotentHint' => true])
        ->and($tools['update_my_profile']['annotations'])->not->toHaveKey('readOnlyHint')
        ->and($tools['show_my_profile'])->toHaveKey('outputSchema');
});

it('reports the app name to clients', function () {
    config(['app.name' => 'Satin Cafe']);
    $token = User::factory()->create()->createToken('Laptop', ['api', 'mcp:use'])->accessToken;

    mcpCall($token, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new stdClass, 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Satin Cafe');
});

it('rate-limits each user', function () {
    $token = User::factory()->create()->createToken('Laptop', ['api', 'mcp:use'])->accessToken;

    foreach (range(1, 60) as $call) {
        mcpCall($token, 'tools/list')->assertOk();
    }

    mcpCall($token, 'tools/list')->assertTooManyRequests();
});

it('records that a change came from the AI client', function () {
    $user = User::factory()->create();
    $token = connectedAppAccessToken($user, oauthApp('Claude'));

    mcpCall($token, 'tools/call', ['name' => 'update_my_profile', 'arguments' => ['name' => 'Ada King', 'email' => $user->email]])
        ->assertOk()
        ->assertJsonPath('result.isError', false);

    $channel = ShareChannel::current();

    expect($channel->kind)->toBe(Channel::MCP)
        ->and($channel->clientName)->toBe('Claude')
        ->and(Context::get(ShareChannel::CONTEXT_KEY)['client_name'])->toBe('Claude');
});

it('records the web channel for a change made in the browser', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Ada King', 'email' => $user->email])
        ->assertRedirect();

    expect(ShareChannel::current()->kind)->toBe(Channel::WEB);
});

it('checks permissions stored on the web guard when an AI client calls a tool', function () {
    $admin = protectedUser();
    $token = $admin->createToken('Laptop', ['api', 'mcp:use'])->accessToken;

    mcpCall($token, 'tools/call', ['name' => 'list_roles', 'arguments' => new stdClass])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.roles.0.name', fn (string $name): bool => $name !== '');
});
