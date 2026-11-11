<?php

namespace ShipKit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ShipKit\Core\Architecture\ProjectLayout;

#[Signature('make:feature {name : The feature name in StudlyCase, such as Billing} {--mcp : Also create the feature\'s MCP tool folder, app/Mcp/Tools/{Name}, for a new feature or an existing one}')]
#[Description('Scaffold a feature: its Actions, Queries, and feature test folders, its agent rule file, and its rules index row')]
class MakeFeatureCommand extends Command
{
    /**
     * Folders every feature starts with.
     *
     * @var list<string>
     */
    private const array FOLDERS = ['app/Actions', 'app/Queries', 'tests/Feature'];

    public function handle(Filesystem $files): int
    {
        $feature = (string) $this->argument('name');

        if ($feature !== Str::studly($feature) || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $feature)) {
            $this->components->error('Feature names are StudlyCase, such as Billing. Did you mean ['.Str::studly($feature).']?');

            return self::FAILURE;
        }

        if (in_array($feature, ProjectLayout::PACKAGE_ACTION_FOLDERS, true)) {
            $this->components->error("{$feature} holds a package's own actions, not a feature. Name the feature after what it does.");

            return self::FAILURE;
        }

        $slug = Str::kebab($feature);
        $withMcp = (bool) $this->option('mcp');
        $exists = $files->isDirectory(base_path("app/Actions/{$feature}")) || $files->isDirectory(base_path("app/Queries/{$feature}")) || $files->exists(base_path(".ai/rules/features/{$slug}.md"));
        $hasTools = $files->isDirectory(base_path("app/Mcp/Tools/{$feature}"));

        if ($exists) {
            return $withMcp && ! $hasTools
                ? $this->addToolsToExistingFeature($files, $feature, $slug)
                : $this->featureExists($feature, $hasTools);
        }

        foreach (self::FOLDERS as $folder) {
            $this->ensureKeptDirectory($files, base_path("{$folder}/{$feature}"));
        }

        $files->ensureDirectoryExists(base_path('.ai/rules/features'));
        $files->put(base_path(".ai/rules/features/{$slug}.md"), str_replace(
            ['{{ feature }}', '{{ title }}'],
            [$feature, Str::headline($feature)],
            $files->get(__DIR__.'/../../stubs/feature/rules.stub'),
        ));

        $this->addIndexRow($files, $feature, $slug);

        if ($withMcp) {
            $this->addToolFolder($files, $feature, $slug);
        }

        $this->components->info("Created the {$feature} feature.");
        $this->components->bulletList([
            "Fill in .ai/rules/features/{$slug}.md with the feature's vocabulary and invariants as they emerge.",
            "Add writes as Actions in app/Actions/{$feature} and reads as Queries in app/Queries/{$feature}; list each one in the rule file.",
            'Put models in app/Models and guard their invariants with ensure...() methods called from guardCreate()/guardUpdate()/guardDelete(), using GuardsInvariants.',
            "Put web controllers and requests in app/Http/Controllers/{$feature} and app/Http/Requests/{$feature}.",
            $withMcp
                ? "Put MCP tools in app/Mcp/Tools/{$feature}, register them in the server, and follow the mcp rule."
                : "If users would act on this feature through an AI client, run `php artisan make:feature {$feature} --mcp` to add its tool folder.",
            'ShipKit\'s architecture checks already cover the new feature. Delete a folder\'s .gitkeep once it holds a class.',
        ]);

        return self::SUCCESS;
    }

    private function featureExists(string $feature, bool $hasTools): int
    {
        $this->components->error("The {$feature} feature already exists.".($hasTools ? '' : " To give it MCP tools, run `php artisan make:feature {$feature} --mcp`."));

        return self::FAILURE;
    }

    /**
     * Give a feature that exists its MCP tool folder, its rule file's tool path, and its index row's tool glob.
     */
    private function addToolsToExistingFeature(Filesystem $files, string $feature, string $slug): int
    {
        $this->addToolFolder($files, $feature, $slug);

        $this->components->info("Added MCP tools to the {$feature} feature.");
        $this->components->bulletList([
            "Put MCP tools in app/Mcp/Tools/{$feature}, register them in the server, and follow the mcp rule.",
            "List each tool in .ai/rules/features/{$slug}.md.",
        ]);

        return self::SUCCESS;
    }

    private function addToolFolder(Filesystem $files, string $feature, string $slug): void
    {
        $this->ensureKeptDirectory($files, base_path("app/Mcp/Tools/{$feature}"));

        $glob = "app/Mcp/Tools/{$feature}/**";
        $this->addGlobToRuleFile($files, $slug, $glob);
        $this->addGlobToIndexRow($files, $slug, $glob);
    }

    private function ensureKeptDirectory(Filesystem $files, string $path): void
    {
        $files->ensureDirectoryExists($path);
        $files->put("{$path}/.gitkeep", '');
    }

    /**
     * Add a path to the `paths:` front matter of the feature's rule file, so the rule loads for it.
     */
    private function addGlobToRuleFile(Filesystem $files, string $slug, string $glob): void
    {
        $path = base_path(".ai/rules/features/{$slug}.md");

        if (! $files->exists($path)) {
            $this->components->warn("There is no .ai/rules/features/{$slug}.md; add '{$glob}' to the paths of the feature's rule file.");

            return;
        }

        $contents = $files->get($path);

        if (str_contains($contents, "'{$glob}'") || ! preg_match('/\A(---\n.*?)(\n---\n)/s', $contents, $match)) {
            return;
        }

        $files->put($path, $match[1]."\n    - '{$glob}'".substr($contents, strlen($match[1])));
    }

    /**
     * Add a glob to the first cell of the feature's row in the rules index, keeping the table aligned when it fits.
     */
    private function addGlobToIndexRow(Filesystem $files, string $slug, string $glob): void
    {
        $indexPath = base_path('.ai/rules/index.md');
        $lines = explode("\n", $files->get($indexPath));
        $key = collect($lines)->keys()->first(fn (int $key): bool => str_starts_with($lines[$key], '|') && str_contains($lines[$key], ".ai/rules/features/{$slug}.md"));

        if ($key === null) {
            $this->components->warn("There is no row for .ai/rules/features/{$slug}.md in .ai/rules/index.md; add '{$glob}' to the row for the feature.");

            return;
        }

        $cells = array_map(trim(...), array_slice(explode('|', $lines[$key]), 1, 2));

        if (str_contains($cells[0], $glob)) {
            return;
        }

        $widths = $this->indexCellWidths($lines);
        $cells[0] .= ", {$glob}";

        $lines[$key] = sprintf('| %s | %s |', str_pad($cells[0], $widths[0]), str_pad($cells[1], $widths[1]));

        $files->put($indexPath, implode("\n", $lines));
    }

    /**
     * Insert the feature's row after the last feature row in the rules index, aligned to the table.
     */
    private function addIndexRow(Filesystem $files, string $feature, string $slug): void
    {
        $indexPath = base_path('.ai/rules/index.md');
        $lines = explode("\n", $files->get($indexPath));
        $widths = $this->indexCellWidths($lines);

        $row = sprintf(
            '| %s | %s |',
            str_pad("app/Actions/{$feature}/**, app/Queries/{$feature}/**", $widths[0]),
            str_pad(".ai/rules/features/{$slug}.md", $widths[1]),
        );

        $insertAfter = collect($lines)
            ->keys()
            ->filter(fn (int $key): bool => str_contains($lines[$key], '.ai/rules/features/'))
            ->last();

        array_splice($lines, $insertAfter === null ? count($lines) : $insertAfter + 1, 0, [$row]);

        $files->put($indexPath, implode("\n", $lines));
    }

    /**
     * The widths of the rules index's two columns, from its header row.
     *
     * @param  list<string>  $lines
     * @return array{int, int}
     */
    private function indexCellWidths(array $lines): array
    {
        $header = collect($lines)->first(fn (string $line): bool => str_starts_with($line, '| Applies to'));

        if ($header === null) {
            return [0, 0];
        }

        [$first, $second] = array_map(fn (string $cell): int => strlen($cell) - 2, array_slice(explode('|', $header), 1, 2));

        return [$first, $second];
    }
}
