<?php

namespace ShipKit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ShipKit\Core\Architecture\ProjectLayout;

#[Signature('make:feature {name : The feature name in StudlyCase, such as Billing}')]
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

        if ($files->isDirectory(base_path("app/Actions/{$feature}")) || $files->isDirectory(base_path("app/Queries/{$feature}")) || $files->exists(base_path(".ai/rules/features/{$slug}.md"))) {
            $this->components->error("The {$feature} feature already exists.");

            return self::FAILURE;
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

        $this->components->info("Created the {$feature} feature.");
        $this->components->bulletList([
            "Fill in .ai/rules/features/{$slug}.md with the feature's vocabulary and invariants as they emerge.",
            "Add writes as Actions in app/Actions/{$feature} and reads as Queries in app/Queries/{$feature}; list each one in the rule file.",
            'Put models in app/Models and guard their invariants with ensure...() methods called from guardCreate()/guardUpdate()/guardDelete(), using GuardsInvariants.',
            "Put web controllers and requests in app/Http/Controllers/{$feature} and app/Http/Requests/{$feature}.",
            'ShipKit\'s architecture checks already cover the new feature. Delete a folder\'s .gitkeep once it holds a class.',
        ]);

        return self::SUCCESS;
    }

    private function ensureKeptDirectory(Filesystem $files, string $path): void
    {
        $files->ensureDirectoryExists($path);
        $files->put("{$path}/.gitkeep", '');
    }

    /**
     * Insert the feature's row after the last feature row in the rules index, aligned to the table.
     */
    private function addIndexRow(Filesystem $files, string $feature, string $slug): void
    {
        $indexPath = base_path('.ai/rules/index.md');
        $lines = explode("\n", $files->get($indexPath));

        $header = collect($lines)->first(fn (string $line): bool => str_starts_with($line, '| Applies to'));
        $cellWidths = $header === null
            ? [0, 0]
            : array_map(fn (string $cell): int => strlen($cell) - 2, array_slice(explode('|', $header), 1, 2));

        $row = sprintf(
            '| %s | %s |',
            str_pad("app/Actions/{$feature}/**, app/Queries/{$feature}/**", $cellWidths[0]),
            str_pad(".ai/rules/features/{$slug}.md", $cellWidths[1]),
        );

        $insertAfter = collect($lines)
            ->keys()
            ->filter(fn (int $key): bool => str_contains($lines[$key], '.ai/rules/features/'))
            ->last();

        array_splice($lines, $insertAfter === null ? count($lines) : $insertAfter + 1, 0, [$row]);

        $files->put($indexPath, implode("\n", $lines));
    }
}
