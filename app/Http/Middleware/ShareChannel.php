<?php

namespace App\Http\Middleware;

use App\Models\OAuthClient;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Passport\AccessToken;
use ShipKit\Core\Channel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Put the request's channel (web, API, or MCP with its OAuth client) into Laravel's Context, so logs
 * and queued jobs record where a change came from, and controllers and tools can pass it to Actions.
 */
class ShareChannel
{
    public const string CONTEXT_KEY = 'channel';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $kind = Channel::WEB): Response
    {
        Context::add(self::CONTEXT_KEY, $this->channel($request, $kind)->toContext());

        return $next($request);
    }

    /**
     * The channel shared for the current request, for controllers and tools to pass to Actions.
     */
    public static function current(): Channel
    {
        /** @var array{kind?: string, client_id?: string|null, client_name?: string|null} $shared */
        $shared = (array) Context::get(self::CONTEXT_KEY, []);

        return match ($shared['kind'] ?? Channel::WEB) {
            Channel::MCP => Channel::mcp((string) ($shared['client_id'] ?? ''), (string) ($shared['client_name'] ?? '')),
            Channel::API => Channel::api($shared['client_id'] ?? null, $shared['client_name'] ?? null),
            default => Channel::web(),
        };
    }

    private function channel(Request $request, string $kind): Channel
    {
        if ($kind === Channel::WEB) {
            return Channel::web();
        }

        $user = $request->user('api');
        $token = $user instanceof User ? $user->token() : null;
        $clientId = $token instanceof AccessToken && isset($token->oauth_client_id) ? (string) $token->oauth_client_id : null;
        // The id is trustworthy; the name is whatever the client called itself when it registered.
        $clientName = $clientId === null ? null : OAuthClient::query()->whereKey($clientId)->value('name');

        return $kind === Channel::MCP
            ? Channel::mcp((string) $clientId, is_string($clientName) ? $clientName : 'Unknown client')
            : Channel::api($clientId, is_string($clientName) ? $clientName : null);
    }
}
