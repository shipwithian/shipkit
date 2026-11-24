<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ShipKit\Core\Architecture\ProjectLayout;

/**
 * A scratch project with the given frontend files, and the primitives it has in components/ui.
 *
 * @param  array<string, string>  $files
 * @param  list<string>  $primitives
 * @param  array<string, string>  $exceptions
 */
function primitivesLayout(array $files, array $primitives = ['button', 'input', 'label', 'select', 'textarea', 'table', 'dialog', 'checkbox'], array $exceptions = []): ProjectLayout
{
    $basePath = sys_get_temp_dir().'/shipkit-primitives-'.Str::uuid();
    $filesystem = new Filesystem;

    foreach ($primitives as $primitive) {
        $files["resources/js/components/ui/{$primitive}.tsx"] = "export function Primitive() { return <{$primitive} />; }\n";
    }

    $files['config/shipkit.php'] = '<?php return '.var_export(['architecture' => ['raw_element_exceptions' => $exceptions]], true).';';

    foreach ($files as $path => $contents) {
        $filesystem->ensureDirectoryExists(dirname("{$basePath}/{$path}"));
        $filesystem->put("{$basePath}/{$path}", $contents);
    }

    return new ProjectLayout($basePath);
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/shipkit-primitives-*', GLOB_ONLYDIR) ?: [] as $path) {
        (new Filesystem)->deleteDirectory($path);
    }
});

it('builds the application\'s screens from its shadcn/ui primitives', function () {
    $violations = ProjectLayout::current()->rawElementViolations();

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('fails on a raw element that a primitive replaces', function (string $markup, string $element, string $primitive) {
    $layout = primitivesLayout(['resources/js/pages/orders/index.tsx' => "export default function Orders() {\n    return (\n        {$markup}\n    );\n}\n"]);

    expect($layout->rawElementViolations())->toBe(["resources/js/pages/orders/index.tsx:3: <{$element}> is hand-built; use {$primitive} from @/components/ui/".strtolower($primitive === 'Button' ? 'button' : Str::kebab($primitive))]);
})->with([
    'a button' => ['<button onClick={save}>Save</button>', 'button', 'Button'],
    'an input' => ['<input name="email" />', 'input', 'Input'],
    'a checkbox' => ['<input type="checkbox" name="agree" />', 'input', 'Checkbox'],
    'a label' => ['<label htmlFor="email">Email</label>', 'label', 'Label'],
    'a select' => ['<select name="size"></select>', 'select', 'Select'],
    'a textarea' => ['<textarea name="note" />', 'textarea', 'Textarea'],
    'a table' => ['<table className="w-full"></table>', 'table', 'Table'],
    'a dialog' => ['<dialog open>Hi</dialog>', 'dialog', 'Dialog'],
]);

it('says how to add a primitive the project does not have yet', function () {
    $layout = primitivesLayout(['resources/js/pages/orders/index.tsx' => "const t = <table />;\n"], primitives: ['button']);

    expect($layout->rawElementViolations())->toBe(['resources/js/pages/orders/index.tsx:1: <table> is hand-built; use Table from @/components/ui/table (add it with `npx shadcn@latest add table`)']);
});

it('reports a table once, not every row and cell', function () {
    $layout = primitivesLayout(['resources/js/pages/orders/partials/orders-table.tsx' => "const t = (\n<table>\n<thead><tr><th>A</th></tr></thead>\n<tbody><tr><td>1</td></tr></tbody>\n</table>\n);\n"]);

    expect($layout->rawElementViolations())->toHaveCount(1);
});

it('passes hidden form fields, the primitives themselves, and components named like elements', function () {
    $layout = primitivesLayout([
        'resources/js/pages/auth/oauth.tsx' => "const f = <form method=\"post\"><input type=\"hidden\" name=\"_token\" value={token} />\n<input\n    type=\"hidden\"\n    name=\"state\"\n/></form>;\n",
        'resources/js/pages/orders/index.tsx' => "const b = <Button><Input /><Label /><Table /><TableRow /></Button>;\nconst s: Array<input> = [];\n",
        'resources/js/components/ui/table.tsx' => "export const Table = () => <table><tr><td /></tr></table>;\n",
    ]);

    expect($layout->rawElementViolations())->toBe([]);
});

it('ignores elements in comments', function () {
    $layout = primitivesLayout(['resources/js/pages/orders/index.tsx' => "// replaced <button> with Button\n/* <table> */\nconst x = 1;\n"]);

    expect($layout->rawElementViolations())->toBe([]);
});

it('skips a file recorded as a deliberate exception', function () {
    $layout = primitivesLayout(
        ['resources/js/pages/print/receipt.tsx' => "const r = <table />;\n"],
        exceptions: ['resources/js/pages/print/receipt.tsx' => 'a printed receipt styled for paper, not the app'],
    );

    expect($layout->rawElementViolations())->toBe([]);
});
