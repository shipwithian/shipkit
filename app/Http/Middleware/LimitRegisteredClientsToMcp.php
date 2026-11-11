<?php

namespace App\Http\Middleware;

use App\Actions\Auth\LimitClientToMcpAction;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * After an AI client registers itself, limit it to the `mcp:use` scope, so connecting an AI client
 * never grants the REST API.
 *
 * It acts only on the registration request; discovery and everything else pass through.
 * Registration and the limit are one transaction, and the client is kept only when both succeed:
 * a failed registration (laravel/mcp answers with an error response instead of throwing), a
 * response with no client id, or a failure to limit it all roll the client back. A client can never
 * exist with unrestricted scopes.
 *
 * It is middleware, not an Action that owns the transaction, because laravel/mcp's controller does
 * the registration and no Action wraps it.
 */
class LimitRegisteredClientsToMcp
{
    public function __construct(private readonly LimitClientToMcpAction $limitClientToMcp) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->is('oauth/register')) {
            return $next($request);
        }

        $level = DB::transactionLevel();

        DB::beginTransaction();

        try {
            $response = $next($request);

            if (! $response->isSuccessful()) {
                DB::rollBack();

                return $response;
            }

            $clientId = $response instanceof JsonResponse ? ($response->getData(true)['client_id'] ?? null) : null;

            if (! is_string($clientId)) {
                throw new RuntimeException('A registered AI client came back without a client id, so it cannot be limited to mcp:use.');
            }

            $this->limitClientToMcp->handle($clientId);

            DB::commit();

            return $response;
        } catch (Throwable $exception) {
            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }

            throw $exception;
        }
    }
}
