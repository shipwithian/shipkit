<?php

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Collections\TransformedCollection;
use Spatie\TypeScriptTransformer\Data\WriteableFile;
use Spatie\TypeScriptTransformer\Formatters\Formatter;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Generates `resources/js/types/generated.ts` from the `#[TypeScript]` classes in `app/Http/Props`.
 * Run `composer types:generate`; `composer ci:check` fails when the committed file differs.
 */
class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->transformer(AttributedClassTransformer::class)
            ->transformDirectories(app_path('Http/Props'))
            ->outputDirectory(resource_path('js/types'))
            ->writer(new class('generated.ts') extends FlatModuleWriter
            {
                /**
                 * Mark the output as generated, so nobody edits it by hand.
                 *
                 * @return array<WriteableFile>
                 */
                public function output(array $transformed, TransformedCollection $transformedCollection): array
                {
                    return array_map(
                        fn (WriteableFile $file): WriteableFile => new WriteableFile(
                            $file->path,
                            "// Generated from app/Http/Props by `composer types:generate`. Do not edit.\n\n".$file->contents,
                        ),
                        parent::output($transformed, $transformedCollection),
                    );
                }
            })
            ->formatter(new class implements Formatter
            {
                /**
                 * Format with the project's own formatter, so `vp check` accepts the output as written.
                 *
                 * @param  array<string>  $files
                 */
                public function format(array $files): void
                {
                    $process = new Process([base_path('node_modules/.bin/vp'), 'fmt', ...$files], base_path());
                    $process->run();

                    if (! $process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }
                }
            })
            ->withoutManifest();
    }
}
