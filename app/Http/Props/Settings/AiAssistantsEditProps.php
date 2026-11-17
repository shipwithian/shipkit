<?php

namespace App\Http\Props\Settings;

use App\Http\Props\PageProps;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for `settings/ai-assistants`: the addresses an AI assistant connects to, and the apps the
 * user has connected. `mcpUrl` acts as the signed-in user; `publicMcpUrl` needs no account.
 */
#[TypeScript]
final readonly class AiAssistantsEditProps extends PageProps
{
    /**
     * @param  list<ConnectedAppListItem>  $connectedApps
     */
    public function __construct(
        public string $serverName,
        public string $mcpUrl,
        public string $publicMcpUrl,
        public array $connectedApps,
    ) {}
}
