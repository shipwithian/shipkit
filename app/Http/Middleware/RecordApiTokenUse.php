<?php

namespace App\Http\Middleware;

use App\Actions\Auth\RecordApiTokenUseAction;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Record when the token authenticating an API request was last used, for the token and connected
 * app lists.
 */
class RecordApiTokenUse
{
    public function __construct(private readonly RecordApiTokenUseAction $recordApiTokenUse) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->token() : null;

        if ($token instanceof AccessToken && isset($token->oauth_access_token_id)) {
            $this->recordApiTokenUse->handle((string) $token->oauth_access_token_id);
        }

        return $next($request);
    }
}
