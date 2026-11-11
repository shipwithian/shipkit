<?php

use App\Http\Middleware\ShareChannel;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use ShipKit\Core\ChannelKind;

it('asks an unauthenticated client to sign in and says where', function () {
    mcpRequest(null, 'tools/list')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
});

it('rejects a token without the mcp:use scope', function () {
    $token = User::factory()->create()->createToken('REST only', ['api'])->accessToken;

    mcpRequest($token, 'tools/list')->assertForbidden();
});

it('lists the tools with their read-only, destructive, and idempotent annotations', function () {
    $token = User::factory()->create()->createToken('Laptop', ['api', 'mcp:use'])->accessToken;

    $tools = collect(mcpRequest($token, 'tools/list')->assertOk()->json('result.tools'))->keyBy('name');

    expect($tools->keys()->all())->toEqualCanonicalizing(['show_my_profile', 'update_my_profile', 'list_roles'])
        ->and($tools['show_my_profile']['annotations'])->toMatchArray(['readOnlyHint' => true, 'idempotentHint' => true])
        ->and($tools['list_roles']['annotations'])->toMatchArray(['readOnlyHint' => true])
        ->and($tools['update_my_profile']['annotations'])->toMatchArray(['destructiveHint' => true, 'idempotentHint' => true])
        ->and($tools['update_my_profile']['annotations'])->not->toHaveKey('readOnlyHint')
        ->and($tools['show_my_profile'])->toHaveKey('outputSchema');
});

it('reports the app name to clients', function () {
    config(['app.name' => 'Satin Cafe']);
    $token = User::factory()->create()->createToken('Laptop', ['api', 'mcp:use'])->accessToken;

    mcpRequest($token, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new stdClass, 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Satin Cafe');
});

it('rate-limits each user', function () {
    $token = User::factory()->create()->createToken('Laptop', ['api', 'mcp:use'])->accessToken;

    foreach (range(1, 60) as $call) {
        mcpRequest($token, 'tools/list')->assertOk();
    }

    mcpRequest($token, 'tools/list')->assertTooManyRequests();
});

it('logs that a change came from the AI client, with its name', function () {
    Log::spy();

    $user = User::factory()->create();
    $token = connectedAppAccessToken($user, oauthApp('Claude'));

    mcpRequest($token, 'tools/call', ['name' => 'update_my_profile', 'arguments' => ['name' => 'Ada King', 'email' => $user->email]])
        ->assertOk()
        ->assertJsonPath('result.isError', false);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'Profile updated via MCP (Claude).'
        && $context['user_id'] === $user->id
        && $context['channel']['kind'] === 'mcp'
        && $context['channel']['client_name'] === 'Claude'
        && $context['changed'] === ['name'])->once();
});

it('shares the AI client id, but not its self-declared name, with every log line', function () {
    $user = User::factory()->create();
    $claude = oauthApp('Claude');
    $token = connectedAppAccessToken($user, $claude);

    mcpRequest($token, 'tools/call', ['name' => 'show_my_profile', 'arguments' => new stdClass])->assertOk();

    expect(Context::get(ShareChannel::CONTEXT_KEY))->toBe(['kind' => 'mcp', 'client_id' => (string) $claude->getKey(), 'client_name' => null]);
});

it('logs that a change came from the web, and shares the web channel', function () {
    Log::spy();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Ada King', 'email' => $user->email])
        ->assertRedirect();

    expect(Context::has(ShareChannel::CONTEXT_KEY))->toBeTrue()
        ->and(Context::get(ShareChannel::CONTEXT_KEY)['kind'])->toBe(ChannelKind::Web->value);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'Profile updated via Web.'
        && $context['channel']['kind'] === 'web'
        && $context['channel']['client_id'] === null)->once();
});
it('checks permissions stored on the web guard when an AI client calls a tool', function () {
    $admin = protectedUser();
    $token = $admin->createToken('Laptop', ['api', 'mcp:use'])->accessToken;

    mcpRequest($token, 'tools/call', ['name' => 'list_roles', 'arguments' => new stdClass])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.roles.0.name', fn (string $name): bool => $name !== '');
});
