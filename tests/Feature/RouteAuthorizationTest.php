<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Authenticated actions that deliberately do not call Gate::authorize(), with the reason.
 * Keyed by "Controller@method" relative to App\Http\Controllers.
 *
 * @var array<string, string>
 */
const ACTIONS_WITHOUT_GATE = [];

it('starts every authenticated controller action with Gate::authorize()', function () {
    $missing = [];

    foreach (appControllerRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        if (! collect($middleware)->contains(fn (mixed $name): bool => is_string($name) && Str::startsWith($name, 'auth'))) {
            continue;
        }

        [$class, $method] = Str::parseCallback($route->getActionName(), '__invoke');
        $action = Str::after($class, 'App\\Http\\Controllers\\')."@{$method}";

        if (array_key_exists($action, ACTIONS_WITHOUT_GATE) || firstStatementAuthorizes($class, $method)) {
            continue;
        }

        $missing[] = "{$action} ({$route->uri()}): make Gate::authorize() its first statement, or list it in ACTIONS_WITHOUT_GATE with a reason";
    }

    expect($missing)->toBeEmpty(implode(PHP_EOL, array_unique($missing)));
});

it('authorizes in controllers and policies, never in route middleware', function () {
    $violations = [];

    foreach (appControllerRoutes() as $route) {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && Str::startsWith($middleware, ['can:', 'role:', 'permission:', 'role_or_permission:'])) {
                $violations[] = "{$route->uri()} uses [{$middleware}]; call Gate::authorize() in the controller instead";
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

/**
 * @return list<RouteDefinition>
 */
function appControllerRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => Str::startsWith($route->getActionName(), 'App\\Http\\Controllers\\'))
        ->values()
        ->all();
}

function firstStatementAuthorizes(string $class, string $method): bool
{
    $file = (new ReflectionClass($class))->getFileName();
    $statements = (new ParserFactory)->createForHostVersion()->parse((string) file_get_contents((string) $file)) ?? [];

    $traverser = new NodeTraverser(new NameResolver);
    $statements = $traverser->traverse($statements);

    $classMethod = (new NodeFinder)->findFirst($statements, fn (Node $node): bool => $node instanceof Node\Stmt\ClassMethod && $node->name->toString() === $method);
    $body = $classMethod instanceof Node\Stmt\ClassMethod ? ($classMethod->stmts ?? []) : [];

    // Reading the actor first (`$user = $request->user();`) acts on nothing, so it may precede the check.
    $first = collect($body)->first(fn (Node\Stmt $statement): bool => ! readsTheActor($statement));

    return $first instanceof Expression
        && $first->expr instanceof StaticCall
        && $first->expr->class instanceof Node\Name
        && $first->expr->class->toString() === Gate::class
        && $first->expr->name instanceof Node\Identifier
        && $first->expr->name->toString() === 'authorize';
}

function readsTheActor(Node\Stmt $statement): bool
{
    return $statement instanceof Expression
        && $statement->expr instanceof Node\Expr\Assign
        && $statement->expr->var instanceof Node\Expr\Variable
        && $statement->expr->expr instanceof Node\Expr\MethodCall
        && $statement->expr->expr->name instanceof Node\Identifier
        && $statement->expr->expr->name->toString() === 'user'
        && $statement->expr->expr->args === [];
}
