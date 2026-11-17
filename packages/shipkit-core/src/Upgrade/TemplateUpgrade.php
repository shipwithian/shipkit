<?php

namespace ShipKit\Core\Upgrade;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Works out what moving the project's template files from one ShipKit release to another does to
 * each file, as a three-way merge: the project's file, the old release's, and the new release's.
 *
 * It fetches the two release tags into temporary refs under `refs/shipkit/upgrade/` in the
 * project's own repository, and always removes them again. Only files the template ships to new
 * projects count (what `git archive` of the tag holds, so the template's export rules apply), and
 * lock files, `shipkit.json`, and the project's excluded paths are left out.
 *
 * Applying writes the result into the working tree and nothing else: it never stages, commits, or
 * touches a file the plan does not name.
 */
class TemplateUpgrade
{
    public const string FROM_REF = 'refs/shipkit/upgrade/from';

    public const string TO_REF = 'refs/shipkit/upgrade/to';

    /**
     * Files an upgrade never touches: package managers regenerate the lock files, and the
     * command stamps `shipkit.json` itself.
     */
    public const array ALWAYS_EXCLUDED = ['composer.lock', 'package-lock.json', TemplateVersion::FILE];

    /**
     * What the upgrade does to each changed template file, and with `$apply`, does it.
     *
     * @param  list<string>  $excluded
     * @return array<string, FileChange> keyed by path, sorted
     *
     * @throws RuntimeException when the upgrade cannot run; nothing has changed
     */
    public function plan(string $from, string $to, string $source, array $excluded = [], bool $apply = false): array
    {
        $this->ensureCanRun($from, $to);
        $this->fetch($from, $to, $source);

        try {
            $plan = $this->classify([...self::ALWAYS_EXCLUDED, ...$excluded]);

            if ($apply) {
                foreach ($plan as $path => $change) {
                    $this->write($path, $change);
                }
            }

            return $plan;
        } finally {
            $this->cleanUp();
        }
    }

    /**
     * The "Upgrading to X.Y" sections of the installed shipkit-core's UPGRADE.md for every minor
     * version after `$from` up to `$to`, oldest first.
     *
     * @return list<string>
     */
    public function notes(string $from, string $to): array
    {
        $path = base_path('vendor/shipwithian/shipkit-core/UPGRADE.md');

        if (! is_file($path)) {
            return [];
        }

        preg_match_all('/^## Upgrading to (\d+\.\d+)\n(.*?)(?=^## |\z)/ms', (string) file_get_contents($path), $sections, PREG_SET_ORDER);

        $notes = [];

        foreach ($sections as [, $version, $body]) {
            if (version_compare("{$version}.0", $this->minor($from).'.0', '>') && version_compare("{$version}.0", $this->minor($to).'.0', '<=')) {
                $notes[$version] = "## Upgrading to {$version}\n\n".trim($body);
            }
        }

        uksort($notes, fn (string $first, string $second): int => version_compare($first, $second));

        return array_values($notes);
    }

    private function ensureCanRun(string $from, string $to): void
    {
        foreach ([$from, $to] as $version) {
            if (! preg_match(TemplateVersion::VERSION_PATTERN, $version)) {
                throw new RuntimeException("Template versions are major.minor.patch, such as 5.3.0; got \"{$version}\".");
            }
        }

        if (! version_compare($to, $from, '>')) {
            throw new RuntimeException("The target version must be newer than the project's: {$to} is not newer than {$from}.");
        }

        if ($this->git('rev-parse --is-inside-work-tree')->failed()) {
            throw new RuntimeException('shipkit:upgrade needs the project to be a Git repository, so that every change it makes can be reviewed and undone.');
        }

        if (trim($this->git('status --porcelain --untracked-files=no')->output()) !== '') {
            throw new RuntimeException('The project has uncommitted changes. Commit or stash them first, so the upgrade\'s changes are the only ones in the working tree.');
        }
    }

    private function fetch(string $from, string $to, string $source): void
    {
        $fetch = $this->git('fetch --quiet --no-tags '.escapeshellarg($source).' '.escapeshellarg("+refs/tags/v{$from}:".self::FROM_REF).' '.escapeshellarg("+refs/tags/v{$to}:".self::TO_REF));

        if ($fetch->failed()) {
            $this->cleanUp();

            throw new RuntimeException("Could not fetch v{$from} and v{$to} from {$source}: ".$this->firstLine($fetch->errorOutput()));
        }
    }

    /**
     * @param  list<string>  $excluded
     * @return array<string, FileChange>
     */
    private function classify(array $excluded): array
    {
        $shippedBefore = $this->shippedFiles(self::FROM_REF);
        $shippedAfter = $this->shippedFiles(self::TO_REF);
        $binary = $this->binaryChanges();
        $plan = [];

        foreach (explode("\n", trim($this->git('diff --name-status --no-renames '.self::FROM_REF.' '.self::TO_REF)->output())) as $line) {
            if (! preg_match('/^([AMDT])\t(.+)$/', $line, $match)) {
                continue;
            }

            [, $status, $path] = $match;
            $shipped = $status === 'D' ? isset($shippedBefore[$path]) : isset($shippedAfter[$path]);

            if (! $shipped || $this->isExcluded($path, $excluded)) {
                continue;
            }

            $plan[$path] = match ($status) {
                'A' => $this->added($path),
                'D' => $this->removed($path),
                default => $this->modified($path, isset($binary[$path])),
            };
        }

        ksort($plan);

        return $plan;
    }

    private function added(string $path): FileChange
    {
        $ours = $this->ours($path);

        if ($ours === null) {
            return FileChange::New;
        }

        return $ours === $this->blob(self::TO_REF, $path) ? FileChange::AlreadyApplied : FileChange::Conflicts;
    }

    private function removed(string $path): FileChange
    {
        $ours = $this->ours($path);

        if ($ours === null) {
            return FileChange::AlreadyApplied;
        }

        return $ours === $this->blob(self::FROM_REF, $path) ? FileChange::Removed : FileChange::Conflicts;
    }

    private function modified(string $path, bool $binary): FileChange
    {
        $ours = $this->ours($path);

        if ($ours === null) {
            return FileChange::DeletedInProject;
        }

        $base = $this->blob(self::FROM_REF, $path);
        $theirs = $this->blob(self::TO_REF, $path);

        if ($ours === $theirs) {
            return FileChange::AlreadyApplied;
        }

        if ($ours === $base) {
            return FileChange::Updates;
        }

        if ($binary) {
            return FileChange::Conflicts;
        }

        return $this->merge($ours, $base, $theirs)['conflicts'] === 0 ? FileChange::MergesCleanly : FileChange::Conflicts;
    }

    /**
     * Bring one file to what the plan says. A conflict keeps the project's version of a binary
     * file and of a file the template removed, and writes Git's conflict markers into a text file.
     */
    private function write(string $path, FileChange $change): void
    {
        $file = base_path($path);
        $ours = $this->ours($path);
        $removedByTemplate = ! $this->existsIn(self::TO_REF, $path);

        match ($change) {
            FileChange::Updates, FileChange::New => $this->writeFromTemplate($path),
            FileChange::MergesCleanly => file_put_contents($file, $this->merge((string) $ours, $this->blob(self::FROM_REF, $path), $this->blob(self::TO_REF, $path))['contents']),
            FileChange::Removed => @unlink($file),
            FileChange::Conflicts => $removedByTemplate || $this->isBinary($path)
                ? null
                : file_put_contents($file, $this->merge((string) $ours, $this->existsIn(self::FROM_REF, $path) ? $this->blob(self::FROM_REF, $path) : '', $this->blob(self::TO_REF, $path))['contents']),
            FileChange::DeletedInProject, FileChange::AlreadyApplied => null,
        };
    }

    /**
     * Write the new release's version of a file, with its mode: executable, or a symbolic link.
     */
    private function writeFromTemplate(string $path): void
    {
        $file = base_path($path);
        $mode = substr($this->git('ls-tree '.self::TO_REF.' -- '.escapeshellarg($path))->output(), 0, 6);

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        if (is_link($file) || is_file($file)) {
            unlink($file);
        }

        if ($mode === '120000') {
            symlink($this->blob(self::TO_REF, $path), $file);

            return;
        }

        file_put_contents($file, $this->blob(self::TO_REF, $path));
        chmod($file, $mode === '100755' ? 0755 : 0644);
    }

    private function existsIn(string $ref, string $path): bool
    {
        return trim($this->git('ls-tree '.$ref.' -- '.escapeshellarg($path))->output()) !== '';
    }

    private function isBinary(string $path): bool
    {
        return isset($this->binaryChanges()[$path]) || str_contains((string) $this->ours($path), "\0");
    }

    /**
     * A three-way merge of one file's contents, with Git's conflict markers where both sides
     * changed the same lines.
     *
     * @return array{contents: string, conflicts: int}
     */
    public function merge(string $ours, string $base, string $theirs): array
    {
        $directory = sys_get_temp_dir().'/shipkit-merge-'.bin2hex(random_bytes(6));
        mkdir($directory);

        try {
            foreach (['ours' => $ours, 'base' => $base, 'theirs' => $theirs] as $name => $contents) {
                file_put_contents("{$directory}/{$name}", $contents);
            }

            $result = Process::path($directory)->run('git merge-file -p -L yours -L "template before" -L "template after" ours base theirs');

            if ($result->exitCode() === null || $result->exitCode() < 0 || $result->exitCode() > 127) {
                throw new RuntimeException('git merge-file failed: '.$this->firstLine($result->errorOutput()));
            }

            return ['contents' => $result->output(), 'conflicts' => (int) $result->exitCode()];
        } finally {
            foreach (['ours', 'base', 'theirs'] as $name) {
                @unlink("{$directory}/{$name}");
            }

            @rmdir($directory);
        }
    }

    /**
     * The project's file as it is in the working tree, or null when it does not exist.
     */
    public function ours(string $path): ?string
    {
        $file = base_path($path);

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    public function blob(string $ref, string $path): string
    {
        return $this->git('show '.escapeshellarg("{$ref}:{$path}"))->output();
    }

    /**
     * @return array<string, true>
     */
    private function shippedFiles(string $ref): array
    {
        $listing = $this->git("archive --format=tar {$ref} | tar -tf -");

        return array_fill_keys(array_filter(explode("\n", $listing->output()), fn (string $path): bool => $path !== '' && ! str_ends_with($path, '/')), true);
    }

    /**
     * @return array<string, true>
     */
    private function binaryChanges(): array
    {
        preg_match_all('/^-\t-\t(.+)$/m', $this->git('diff --numstat --no-renames '.self::FROM_REF.' '.self::TO_REF)->output(), $matches);

        return array_fill_keys($matches[1], true);
    }

    /**
     * @param  list<string>  $excluded
     */
    private function isExcluded(string $path, array $excluded): bool
    {
        foreach ($excluded as $exclusion) {
            $exclusion = trim($exclusion, '/');

            if ($path === $exclusion || str_starts_with($path, "{$exclusion}/")) {
                return true;
            }
        }

        return false;
    }

    private function cleanUp(): void
    {
        $this->git('update-ref -d '.self::FROM_REF);
        $this->git('update-ref -d '.self::TO_REF);
    }

    private function minor(string $version): string
    {
        return implode('.', array_slice(explode('.', $version), 0, 2));
    }

    private function git(string $arguments): ProcessResult
    {
        return Process::path(base_path())->run("git {$arguments}");
    }

    private function firstLine(string $output): string
    {
        $line = trim(strtok(trim($output), "\n") ?: '');

        return $line === '' ? 'git reported an error.' : $line;
    }
}
