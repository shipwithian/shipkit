<?php

namespace ShipKit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

#[Signature('make:context {name : The bounded context name in StudlyCase, such as Billing}')]
#[Description('Scaffold a bounded context: domain folders, its agent rule file, its rules index row, and its feature test folder')]
class MakeContextCommand extends Command
{
    /**
     * Folders every context starts with.
     *
     * @var list<string>
     */
    private const array DOMAIN_FOLDERS = ['Actions', 'Queries', 'Models', 'Exceptions', 'Policies'];

    public function handle(Filesystem $files): int
    {
        $context = (string) $this->argument('name');

        if ($context !== Str::studly($context) || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $context)) {
            $this->components->error('Context names are StudlyCase, such as Billing. Did you mean ['.Str::studly($context).']?');

            return self::FAILURE;
        }

        if ($context === 'Shared') {
            $this->components->error('Shared is not a context: the shared kernel lives in shipwithian/shipkit-core. Name the context after its business capability.');

            return self::FAILURE;
        }

        if ($files->isDirectory(base_path("app/Domain/{$context}"))) {
            $this->components->error("The {$context} context already exists.");

            return self::FAILURE;
        }

        $slug = Str::kebab($context);

        foreach (self::DOMAIN_FOLDERS as $folder) {
            $this->ensureKeptDirectory($files, base_path("app/Domain/{$context}/{$folder}"));
        }

        $this->ensureKeptDirectory($files, base_path("tests/Feature/{$context}"));

        $files->ensureDirectoryExists(base_path('.ai/rules/contexts'));
        $files->put(base_path(".ai/rules/contexts/{$slug}.md"), str_replace(
            ['{{ context }}', '{{ title }}'],
            [$context, Str::headline($context)],
            $files->get(__DIR__.'/../../stubs/context/rules.stub'),
        ));

        $this->addIndexRow($files, $context, $slug);

        $this->components->info("Created the {$context} context.");
        $this->components->bulletList([
            "Fill in .ai/rules/contexts/{$slug}.md with the context's vocabulary as it emerges.",
            "Add Actions and Queries under app/Domain/{$context}; list each one in the rule file.",
            'Guard aggregate invariants with ensure...() methods called from guardCreate()/guardUpdate()/guardDelete(), and use GuardsInvariants.',
            "Put web controllers and requests in app/Http/Controllers/{$context} and app/Http/Requests/{$context}.",
            'ShipKit\'s architecture checks already cover the new context. Delete a folder\'s .gitkeep once it holds a class.',
        ]);

        return self::SUCCESS;
    }

    private function ensureKeptDirectory(Filesystem $files, string $path): void
    {
        $files->ensureDirectoryExists($path);
        $files->put("{$path}/.gitkeep", '');
    }

    /**
     * Insert the context's row after the last context row in the rules index, aligned to the table.
     */
    private function addIndexRow(Filesystem $files, string $context, string $slug): void
    {
        $indexPath = base_path('.ai/rules/index.md');
        $lines = explode("\n", $files->get($indexPath));

        $header = collect($lines)->first(fn (string $line): bool => str_starts_with($line, '| Applies to'));
        $cellWidths = $header === null
            ? [0, 0]
            : array_map(fn (string $cell): int => strlen($cell) - 2, array_slice(explode('|', $header), 1, 2));

        $row = sprintf(
            '| %s | %s |',
            str_pad("app/Domain/{$context}/**", $cellWidths[0]),
            str_pad(".ai/rules/contexts/{$slug}.md", $cellWidths[1]),
        );

        $insertAfter = collect($lines)
            ->keys()
            ->filter(fn (int $key): bool => str_contains($lines[$key], '.ai/rules/contexts/') || str_starts_with($lines[$key], '| app/Domain/** '))
            ->last();

        array_splice($lines, $insertAfter === null ? count($lines) : $insertAfter + 1, 0, [$row]);

        $files->put($indexPath, implode("\n", $lines));
    }
}
