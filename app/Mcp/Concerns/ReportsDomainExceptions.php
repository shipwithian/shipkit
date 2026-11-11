<?php

namespace App\Mcp\Concerns;

use Closure;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use ShipKit\Core\Exceptions\DomainException;

/**
 * Laravel MCP reports any exception other than validation and authorization as "An internal server
 * error occurred.", so a broken business rule would reach the AI client with no reason. Run the
 * tool's work through this to return the rule as "{field}: {message}" instead.
 */
trait ReportsDomainExceptions
{
    /**
     * @param  Closure(): (Response|ResponseFactory)  $work
     */
    protected function reportingDomainExceptions(Closure $work): Response|ResponseFactory
    {
        try {
            return $work();
        } catch (DomainException $exception) {
            return Response::error("{$exception->field()}: {$exception->getMessage()}");
        }
    }
}
