<?php

use ShipKit\Core\Architecture\ProjectLayout;

it('keeps every frontend file short enough to read in one sitting', function () {
    $layout = ProjectLayout::current();
    /** @var array<string, string> $exceptions */
    $exceptions = (array) $layout->config('architecture.long_frontend_files', []);
    $violations = [];

    foreach ($layout->frontendFiles() as $relative) {
        $limit = frontendLineLimit($relative);

        if ($limit === null || array_key_exists($relative, $exceptions)) {
            continue;
        }

        $lines = count(file($layout->path($relative)) ?: []);

        if ($lines > $limit) {
            $violations[] = "{$relative} has {$lines} lines, over the {$limit}-line limit for ".(frontendIsPage($relative) ? 'a page' : 'a frontend file').'. Move each section, dialog, table, or form into its own partial in the feature\'s partials/ folder, and state and handlers into a use-{feature}.ts hook beside it, so the page only reads its props and composes them. List a file that must stay long in shipkit.architecture.long_frontend_files with the reason';
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('gives pages a tighter limit than the pieces they compose', function (string $relative, ?int $limit) {
    expect(frontendLineLimit($relative))->toBe($limit);
})->with([
    'a page' => ['resources/js/pages/roles/index.tsx', 200],
    'a nested page' => ['resources/js/pages/settings/two-factor/show.tsx', 200],
    'a partial' => ['resources/js/pages/roles/partials/roles-table.tsx', 400],
    'a shared component' => ['resources/js/components/app-header.tsx', 400],
    'a hook' => ['resources/js/hooks/use-appearance.ts', 400],
    'a vendored UI primitive' => ['resources/js/components/ui/sidebar.tsx', null],
]);

/**
 * The most lines a frontend file may have, or null for files the project does not write itself.
 */
function frontendLineLimit(string $relative): ?int
{
    if (str_starts_with($relative, 'resources/js/components/ui/')) {
        return null;
    }

    return frontendIsPage($relative) ? 200 : 400;
}

/**
 * Whether a file is a routable page rather than a piece one composes.
 */
function frontendIsPage(string $relative): bool
{
    return str_starts_with($relative, 'resources/js/pages/') && ! str_contains($relative, '/partials/');
}
