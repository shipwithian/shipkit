<?php

namespace ShipKit\Core\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use ShipKit\Core\Upgrade\FileChange;
use ShipKit\Core\Upgrade\TemplateUpgrade;
use ShipKit\Core\Upgrade\TemplateVersion;

/**
 * Shows, and with --apply makes, the changes that bring the project's template files from the
 * ShipKit release it was created from (or last upgraded to) to a newer one.
 */
#[Signature('shipkit:upgrade
    {--from= : The template version the project is on, when shipkit.json does not record it}
    {--to= : The template version to move to; the installed shipkit-core version by default}
    {--source= : Where to fetch the template from; ShipKit on GitHub unless shipkit.json names another}
    {--apply : Make the changes, instead of only showing them}')]
#[Description('Show, and with --apply make, the changes that move the project\'s template files to a newer ShipKit release')]
class UpgradeCommand extends Command
{
    public function handle(TemplateVersion $template, TemplateUpgrade $upgrade): int
    {
        try {
            $from = $this->stringOption('from') ?? $template->version();
            $to = $this->stringOption('to') ?? $this->installedCoreVersion();
            $source = $this->stringOption('source') ?? $template->source();

            if ($from === null) {
                throw new RuntimeException(TemplateVersion::FILE.' records no template version. Pass the version the project was created from or last upgraded to, such as --from=5.2.0.');
            }

            if ($to === null) {
                throw new RuntimeException('The installed shipkit-core has no release version. Pass the template version to move to, such as --to=5.3.0.');
            }

            $apply = (bool) $this->option('apply');
            $plan = $upgrade->plan($from, $to, $source, $template->excluded(), $apply);
        } catch (RuntimeException|InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($from, $to, $plan);

        foreach ($upgrade->notes($from, $to) as $note) {
            $this->newLine();
            $this->line($note);
        }

        $this->newLine();

        if (! $apply) {
            $this->components->info('Nothing was changed. Run again with --apply to make these changes.');

            return self::SUCCESS;
        }

        $template->stamp($to);
        $this->finish($to, $plan);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, FileChange>  $plan
     */
    private function finish(string $to, array $plan): void
    {
        $conflicts = array_keys(array_filter($plan, fn (FileChange $change): bool => $change === FileChange::Conflicts));

        $this->components->info("Moved the template files to {$to} and recorded it in ".TemplateVersion::FILE.'. Nothing was staged or committed; `git diff` shows every change.');

        if ($conflicts !== []) {
            $this->components->warn('Resolve these files by hand. A text file has Git\'s conflict markers; a binary file, or one the template removed, kept your version, so compare it with the release:');
            $this->components->bulletList($conflicts);
        }

        $this->components->info('Then:');
        $this->components->bulletList([
            'composer update, then npm install, to bring the dependencies the new composer.json and package.json ask for',
            'php artisan migrate, for any new migrations',
            'Follow the upgrade notes above for what files cannot carry: keys, environment variables, data',
            'composer ci:check',
        ]);
    }

    /**
     * @param  array<string, FileChange>  $plan
     */
    private function report(string $from, string $to, array $plan): void
    {
        if ($plan === []) {
            $this->components->info("The template files the project has are the same in {$from} and {$to}.");

            return;
        }

        $this->components->info("Template files that changed from {$from} to {$to}:");

        foreach ($plan as $path => $change) {
            $this->components->twoColumnDetail($path, $change->value);
        }

        $counts = array_count_values(array_map(fn (FileChange $change): string => $change->value, $plan));
        $this->newLine();
        $this->line('  '.implode(', ', array_map(fn (string $change, int $count): string => "{$count} {$change}", array_keys($counts), $counts)).'.');
    }

    private function installedCoreVersion(): ?string
    {
        $version = ltrim((string) InstalledVersions::getPrettyVersion('shipwithian/shipkit-core'), 'v');

        return preg_match(TemplateVersion::VERSION_PATTERN, $version) ? $version : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
