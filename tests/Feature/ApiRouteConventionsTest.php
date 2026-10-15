<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * API routes that deliberately receive a route-bound model, with the reason.
 * Keyed by "METHOD uri".
 *
 * @var array<string, string>
 */
const API_ROUTES_WITH_MODEL_BINDING = [];

it('throttles every API route that changes state', function () {
    $unthrottled = [];

    foreach (apiRoutes() as $route) {
        $changesState = array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];
        $throttled = collect($route->gatherMiddleware())
            ->contains(fn (mixed $middleware): bool => is_string($middleware) && Str::startsWith($middleware, 'throttle'));

        if ($changesState && ! $throttled) {
            $unthrottled[] = "{$route->methods()[0]} {$route->uri()}: add ->middleware('throttle:api-write') or a named limiter for this route";
        }
    }

    expect($unthrottled)->toBeEmpty(implode(PHP_EOL, $unthrottled));
});

it('looks up records for API routes through the actor instead of route model binding', function () {
    $bound = [];

    foreach (apiRoutes() as $route) {
        $key = "{$route->methods()[0]} {$route->uri()}";

        if (array_key_exists($key, API_ROUTES_WITH_MODEL_BINDING)) {
            continue;
        }

        foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
            $bound[] = "{$key} binds \${$parameter->getName()} directly: take the id and resolve it through the actor's relation or a Query scoped to the actor so another user's record is indistinguishable from a missing one, or list the route in API_ROUTES_WITH_MODEL_BINDING with a reason";
        }
    }

    expect($bound)->toBeEmpty(implode(PHP_EOL, $bound));
});

/**
 * @return list<RouteDefinition>
 */
function apiRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => Str::startsWith($route->uri(), 'api/')
            && Str::startsWith($route->getActionName(), 'App\\Http\\Controllers\\'))
        ->values()
        ->all();
}
