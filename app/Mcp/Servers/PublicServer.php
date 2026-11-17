<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AppInfo\ShowAppInfoTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Version;
use ShipKit\Core\Mcp\ForGuests;

/**
 * The app's public MCP server, at `/mcp/public`, for visitors who have not signed in. It has no
 * authentication, so every tool is read-only and returns only what anyone may see; the
 * `ForGuests` marker makes ShipKit's checks hold that.
 */
#[Version('1.0.0')]
#[ForGuests]
class PublicServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
        These tools are public and answer questions about the app without signing in. They cannot see or
        change anyone's account. To act as a signed-in user, connect the address that show_app_info
        returns as signed_in_server_url.
        MARKDOWN;

    /**
     * @var array<int, class-string<Server\Tool>>
     */
    protected array $tools = [
        ShowAppInfoTool::class,
    ];

    protected function boot(): void
    {
        $this->name = (string) config('app.name');
        $this->instructions = "{$this->name}: ".$this->instructions;
    }
}
