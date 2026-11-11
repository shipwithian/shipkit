<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Queries\Auth\OAuthClientNameQuery;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Passport\AccessToken;
use ShipKit\Core\Channel;
use ShipKit\Core\ChannelKind;
use Symfony\Component\HttpFoundation\Response;

/**
 * Put the request's channel (web, API, or MCP with its OAuth client) into Laravel's Context, so
 * every log line records where a change came from. Controllers and tools type-hint `Channel` to
 * receive it and pass it to the Actions that record it.
 *
 * The context carries the client id, which is trustworthy. The name is looked up only when a
 * `Channel` is resolved, because it is whatever the client called itself when it registered.
 */
class ShareChannel
{
    public const string CONTEXT_KEY = 'channel';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $kind = 'web'): Response
    {
        $kind = ChannelKind::from($kind);

        $channel = match ($kind) {
            ChannelKind::Web => Channel::web(),
            ChannelKind::Api => Channel::api($this->clientId($request)),
            ChannelKind::Mcp => Channel::mcp((string) $this->clientId($request)),
        };

        Context::add(self::CONTEXT_KEY, $channel->toContext());

        return $next($request);
    }

    /**
     * The channel of the current request, with the client's name, for the container to inject.
     */
    public static function current(): Channel
    {
        /** @var array{kind?: string, client_id?: string|null, client_name?: string|null} $shared */
        $shared = (array) Context::get(self::CONTEXT_KEY, []);
        $channel = Channel::fromContext($shared);

        return $channel->clientId === null
            ? $channel
            : $channel->withClientName(app(OAuthClientNameQuery::class)->handle($channel->clientId));
    }

    private function clientId(Request $request): ?string
    {
        $user = $request->user('api');
        $token = $user instanceof User ? $user->token() : null;

        return $token instanceof AccessToken && isset($token->oauth_client_id) ? (string) $token->oauth_client_id : null;
    }
}
