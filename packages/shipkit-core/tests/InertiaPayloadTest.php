<?php

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ShipKit\Core\Architecture\ProjectLayout;

it('renders Inertia pages with a typed props object, never an array', function () {
    $layout = ProjectLayout::current();
    $parser = (new ParserFactory)->createForHostVersion();
    $finder = new NodeFinder;
    $violations = [];

    foreach ([...$layout->phpFiles('app'), ...$layout->phpFiles('routes')] as $path) {
        $statements = $parser->parse((string) file_get_contents($path)) ?? [];

        /** @var list<Node\Expr\StaticCall|Node\Expr\FuncCall> $calls */
        $calls = $finder->find($statements, fn (Node $node): bool => inertiaPropsArgument($node) !== null);

        foreach ($calls as $call) {
            $props = inertiaPropsArgument($call);

            if (! $props instanceof Node\Expr\New_) {
                $violations[] = Str::after($path, $layout->path().'/').':'.$call->getStartLine().' passes Inertia untyped props. Pass `new {Page}Props(...)`, a readonly class marked #[TypeScript] whose public properties are the props, so `composer types:generate` gives the page its TypeScript type; a page with no props of its own passes none';
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

/**
 * The props argument of an `Inertia::render()`, `inertia()`, or `Route::inertia()` call, or null
 * when the node is not one of those calls or passes no props.
 */
function inertiaPropsArgument(Node $node): ?Node\Expr
{
    $position = match (true) {
        $node instanceof Node\Expr\StaticCall
            && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier => match ([$node->class->getLast(), $node->name->toString()]) {
                ['Inertia', 'render'] => 1,
                ['Route', 'inertia'] => 2,
                default => null,
            },
        $node instanceof Node\Expr\FuncCall
            && $node->name instanceof Node\Name
            && $node->name->getLast() === 'inertia' => 1,
        default => null,
    };

    if ($position === null) {
        return null;
    }

    /** @var Node\Expr\StaticCall|Node\Expr\FuncCall $node */
    $argument = $node->getArgs()[$position] ?? null;

    return $argument?->value;
}
