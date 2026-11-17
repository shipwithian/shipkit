<?php

namespace App\Mcp\Tools\AppInfo;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_app_info')]
#[Title('Show app info')]
#[Description('What this app is, and the address to connect to act as a signed-in user. Needs no account.')]
#[IsReadOnly]
#[IsIdempotent]
class ShowAppInfoTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        Gate::forUser($user)->authorize('viewAppInfo');

        $description = config('mcp.public_description');

        return Response::structured([
            'name' => (string) config('app.name'),
            'description' => is_string($description) && $description !== '' ? $description : null,
            'signed_in_server_url' => url('/mcp'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'description' => $schema->string()->nullable()->description('What the app does, in its own words; null when it has not set one.')->required(),
            'signed_in_server_url' => $schema->string()->description('Connect this address, and sign in, to let an assistant act as you.')->required(),
        ];
    }
}
