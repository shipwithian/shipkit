<?php

namespace App\Http\Middleware;

use App\Actions\Auth\LimitClientToMcpAction;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an AI client registers itself, limit it to the `mcp:use` scope, so connecting an AI client
 * never grants the REST API.
 */
class LimitRegisteredClientsToMcp
{
    public function __construct(private readonly LimitClientToMcpAction $limitClientToMcp) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            $clientId = $response->getData(true)['client_id'] ?? null;

            if (is_string($clientId)) {
                $this->limitClientToMcp->handle($clientId);
            }
        }

        return $response;
    }
}
