<?php

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\AppInfo\ShowAppInfoTool;
use Illuminate\Testing\TestResponse;

/**
 * A JSON-RPC call to the public MCP server, with no token.
 *
 * @param  array<string, mixed>  $params
 */
function publicMcpRequest(string $method, array $params = []): TestResponse
{
    return test()->postJson('/mcp/public', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
}

it('tells a signed-out visitor about the app and where to connect as themselves', function () {
    config(['app.name' => 'Satin Cafe', 'mcp.public_description' => 'A cafe in Manila. Order ahead for pickup.']);

    PublicServer::tool(ShowAppInfoTool::class)
        ->assertOk()
        ->assertStructuredContent([
            'name' => 'Satin Cafe',
            'description' => 'A cafe in Manila. Order ahead for pickup.',
            'signed_in_server_url' => url('/mcp'),
        ]);
});

it('answers without a description when the app has none', function () {
    config(['mcp.public_description' => null]);

    PublicServer::tool(ShowAppInfoTool::class)
        ->assertOk()
        ->assertStructuredContentPath('description', null);
});

it('answers over HTTP without a token', function () {
    publicMcpRequest('tools/call', ['name' => 'show_app_info', 'arguments' => new stdClass])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.signed_in_server_url', url('/mcp'));
});

it('lists only read-only public tools, none that act as a user', function () {
    $tools = collect(publicMcpRequest('tools/list')->assertOk()->json('result.tools'))->keyBy('name');

    expect($tools->keys()->all())->toBe(['show_app_info'])
        ->and($tools['show_app_info']['annotations'])->toMatchArray(['readOnlyHint' => true]);
});

it('keeps the signed-in server behind sign-in', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});

it('rate-limits each visitor', function () {
    foreach (range(1, 30) as $call) {
        publicMcpRequest('tools/list')->assertOk();
    }

    publicMcpRequest('tools/list')->assertTooManyRequests();
});
