<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AccessControl\ListRolesTool;
use App\Mcp\Tools\Settings\ShowMyProfileTool;
use App\Mcp\Tools\Settings\UpdateMyProfileTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The app's MCP server, at `/mcp`. Every tool acts as the signed-in user.
 */
#[Version('1.0.0')]
class AppServer extends Server
{
    protected string $instructions = <<<'MARKDOWN'
        Tools act as the signed-in user and can do only what that user can do in the app. Read-only tools
        never change anything. A tool marked destructive first returns a summary and a confirmation_token;
        show the summary to the user, and call the tool again with the token only after they agree.
        MARKDOWN;

    /**
     * @var array<int, class-string<Server\Tool>>
     */
    protected array $tools = [
        ShowMyProfileTool::class,
        UpdateMyProfileTool::class,
        ListRolesTool::class,
    ];

    protected function boot(): void
    {
        $this->name = (string) config('app.name');
    }
}
