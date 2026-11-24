<?php

namespace ShipKit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ShipKit\Core\Console\Concerns\ReadsGitHubRepository;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Runs the `review-diff` skill on the current branch's pull request in a fresh, read-only Claude
 * Code session, which did not write the code, then publishes the result where GitHub can enforce
 * it: the report as a pull request comment (updated on each run) and a `shipkit/review` commit
 * status on the reviewed commit. Make that status required on the integration branch, and a pull
 * request cannot merge without an independent review of the exact commit that merges.
 */
#[Signature('shipkit:review
    {--pr= : The pull request to review; the current branch\'s by default}
    {--issue= : The spec issue to review against; read from the pull request\'s "Implements #" or "Closes #" by default}
    {--dry-run : Print the review without publishing it}')]
#[Description('Review the current pull request with review-diff in a fresh session, and publish the verdict to GitHub')]
class ReviewCommand extends Command
{
    use ReadsGitHubRepository;

    public const string STATUS_CONTEXT = 'shipkit/review';

    public const string COMMENT_MARKER = '<!-- shipkit:review -->';

    /**
     * Each verdict of review-diff's handoff, with its label and the commit status it sets.
     */
    public const array VERDICTS = [
        'agent-may-merge' => ['Agent may merge', 'success'],
        'needs-maintainer' => ['Needs the maintainer', 'success'],
        'not-ready' => ['Not ready', 'failure'],
    ];

    /**
     * What the reviewer session may do: read files and spawn the two reviewers. The command gathers
     * the diff, the commit list, the spec, and CI itself and hands them over as files, so neither
     * the session nor its reviewers needs a shell, which sub-agents of a headless session are not
     * reliably allowed.
     */
    public const string ALLOWED_TOOLS = 'Read Glob Grep Agent Skill';

    /**
     * Where the material for one review is written, inside the project's gitignored cache.
     */
    public const string MATERIAL_DIRECTORY = 'framework/cache/shipkit-review';

    public function handle(ExecutableFinder $executables): int
    {
        try {
            foreach (['gh' => 'https://cli.github.com', 'claude' => 'https://code.claude.com'] as $program => $site) {
                if ($executables->find($program) === null) {
                    throw new RuntimeException("{$program} is not installed; shipkit:review needs it. Install it from {$site}.");
                }
            }

            $repository = $this->gitHubRepository() ?? throw new RuntimeException('The origin remote is not on GitHub, so there is no pull request to review.');

            $status = Process::run('git status --porcelain --untracked-files=no');

            if ($status->failed()) {
                throw new RuntimeException('git status failed: '.trim($status->errorOutput()));
            }

            if (trim($status->output()) !== '') {
                throw new RuntimeException('The working tree has uncommitted changes. Commit and push them first: the review covers what the pull request would merge.');
            }

            $pull = $this->pullRequest();

            $head = Process::run('git rev-parse HEAD');

            if ($head->failed()) {
                throw new RuntimeException('git rev-parse HEAD failed: '.trim($head->errorOutput()));
            }

            if (trim($head->output()) !== $pull['headRefOid']) {
                throw new RuntimeException("This checkout is not at pull request #{$pull['number']}'s latest commit. Push your commits, or pull the branch, so the review covers what would merge.");
            }

            $issue = $this->stringOption('issue') ?? $this->specIssue($pull['body']);
            $material = $this->gatherMaterial($pull, $issue);

            try {
                $review = $this->review($pull['baseRefName'], $issue, $material);
            } finally {
                $this->clearMaterial();
            }
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        [$label, $state] = self::VERDICTS[$review['verdict']];

        $this->line($review['report']);
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->components->info("Verdict: {$label}. Nothing was published (--dry-run).");

            return $state === 'success' ? self::SUCCESS : self::FAILURE;
        }

        try {
            $this->publish($repository, $pull, $review['report'], $label, $state);
        } catch (RuntimeException $exception) {
            $this->components->error('Not published: '.$exception->getMessage()." The verdict was {$label}; run it again.");

            return self::FAILURE;
        }

        $state === 'success'
            ? $this->components->info("Verdict: {$label}. Published on pull request #{$pull['number']} and set ".self::STATUS_CONTEXT.' on '.substr($pull['headRefOid'], 0, 7).'.')
            : $this->components->error("Verdict: {$label}. Published on pull request #{$pull['number']}; fix the findings, push, and review again.");

        return $state === 'success' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{number: int, baseRefName: string, headRefOid: string, body: string}
     */
    private function pullRequest(): array
    {
        $number = $this->stringOption('pr');

        if ($number !== null && ! ctype_digit($number)) {
            throw new RuntimeException("--pr takes a pull request number, such as --pr=12; got \"{$number}\".");
        }

        $view = Process::run('gh pr view'.($number === null ? '' : " {$number}").' --json number,baseRefName,headRefOid,body');
        $pull = json_decode($view->output(), true);

        if ($view->failed() || ! is_array($pull) || ! isset($pull['number'], $pull['baseRefName'], $pull['headRefOid'])) {
            throw new RuntimeException('There is no pull request for this branch. Open one first (gh pr create), or pass --pr.');
        }

        return [
            'number' => (int) $pull['number'],
            'baseRefName' => (string) $pull['baseRefName'],
            'headRefOid' => (string) $pull['headRefOid'],
            'body' => (string) ($pull['body'] ?? ''),
        ];
    }

    /**
     * The issue a pull request implements, from "Implements #41" or "Closes #41".
     */
    private function specIssue(string $body): ?string
    {
        return preg_match('/\b(?:implements|closes|fixes|resolves)(?:\s+(?:spec|issue))?\s+#(\d+)/i', $body, $match) ? $match[1] : null;
    }

    /**
     * Write the diff, the commit list, the spec, and the CI state where the reviewers can read
     * them, so they need no shell.
     *
     * @param  array{number: int, baseRefName: string, headRefOid: string, body: string}  $pull
     * @return array<string, string> file name => path relative to the project
     *
     * @throws RuntimeException when the diff cannot be read
     */
    private function gatherMaterial(array $pull, ?string $issue): array
    {
        $base = $pull['baseRefName'];
        $fetch = Process::run('git fetch --quiet origin '.escapeshellarg($base));

        if ($fetch->failed()) {
            throw new RuntimeException("Could not fetch origin/{$base} to compare with: ".trim($fetch->errorOutput()));
        }

        $diff = Process::run('git diff '.escapeshellarg("origin/{$base}...HEAD"));

        if ($diff->failed() || trim($diff->output()) === '') {
            throw new RuntimeException("There is nothing to review between origin/{$base} and this commit.");
        }

        $files = [
            'diff.patch' => $diff->output(),
            'commits.txt' => Process::run('git log --oneline '.escapeshellarg("origin/{$base}..HEAD"))->output(),
            'changed-files.txt' => Process::run('git diff --name-only '.escapeshellarg("origin/{$base}...HEAD"))->output(),
            'checks.txt' => Process::run("gh pr checks {$pull['number']}")->output(),
            'pull-request.md' => $pull['body'],
        ];

        if ($issue !== null) {
            $files['spec.md'] = Process::run('gh issue view '.escapeshellarg($issue).' --comments')->output();
        }

        $directory = storage_path(self::MATERIAL_DIRECTORY);
        $this->clearMaterial();
        mkdir($directory, 0755, true);

        $paths = [];

        foreach ($files as $name => $contents) {
            file_put_contents("{$directory}/{$name}", $contents);
            $paths[$name] = substr("{$directory}/{$name}", strlen(base_path()) + 1);
        }

        return $paths;
    }

    private function clearMaterial(): void
    {
        $directory = storage_path(self::MATERIAL_DIRECTORY);

        foreach (glob("{$directory}/*") ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }

    /**
     * @param  array<string, string>  $material
     * @return array{verdict: string, report: string}
     */
    private function review(string $base, ?string $issue, array $material): array
    {
        $spec = $issue === null
            ? 'There is no spec issue; say so on the Spec axis.'
            : "The spec is GitHub issue #{$issue}, saved in {$material['spec.md']}.";

        $prompt = implode(' ', [
            "Run the review-diff skill on this branch, with fixed point origin/{$base}.",
            "The material is already gathered, so skip the skill's git and gh commands: the diff is {$material['diff.patch']}, the commits {$material['commits.txt']}, the changed files {$material['changed-files.txt']}, CI {$material['checks.txt']}, and the pull request's description, which carries its handoff evidence, {$material['pull-request.md']}.",
            $spec,
            'You are the independent review: you did not write this code.',
            'Spawn the shipkit-standards-reviewer and shipkit-spec-reviewer agents with the Agent tool, in the foreground, as the skill says. Give each the paths of the material files to read instead of commands to run, and report what they find; never review the diff yourself in their place.',
            'Branch protection requires CI separately, so report the CI state from the file, but do not base the verdict on CI that is still running.',
            'Do not edit, stage, commit, push, or comment; this command publishes your report.',
            'Return the full report, as markdown, in `report`, and the Handoff verdict in `verdict`: agent-may-merge, needs-maintainer, or not-ready.',
        ]);

        $schema = (string) json_encode([
            'type' => 'object',
            'properties' => [
                'verdict' => ['type' => 'string', 'enum' => array_keys(self::VERDICTS)],
                'report' => ['type' => 'string'],
            ],
            'required' => ['verdict', 'report'],
        ]);

        $this->components->info('Reviewing in a fresh read-only session. This takes a few minutes.');

        $result = Process::timeout((int) config('shipkit.review.timeout', 1800))->run(implode(' ', [
            'claude -p '.escapeshellarg($prompt),
            '--output-format json',
            '--json-schema '.escapeshellarg($schema),
            '--permission-mode dontAsk',
            '--allowedTools '.escapeshellarg(self::ALLOWED_TOOLS),
            '--model '.escapeshellarg((string) config('shipkit.review.model', 'sonnet')),
            '--effort '.escapeshellarg((string) config('shipkit.review.effort', 'low')),
            '--no-session-persistence',
        ]));

        $output = json_decode($result->output(), true);
        $review = is_array($output) ? ($output['structured_output'] ?? json_decode((string) ($output['result'] ?? ''), true)) : null;

        if ($result->failed() || ! is_array($review) || ! isset(self::VERDICTS[$review['verdict'] ?? '']) || ! is_string($review['report'] ?? null)) {
            $reason = is_array($output) && is_string($output['result'] ?? null) ? $output['result'] : trim($result->errorOutput());

            throw new RuntimeException('The reviewer gave no verdict, so nothing was published. '.($reason === '' ? 'Run it again.' : $reason));
        }

        return ['verdict' => $review['verdict'], 'report' => $review['report']];
    }

    /**
     * @param  array{number: int, baseRefName: string, headRefOid: string, body: string}  $pull
     */
    private function publish(string $repository, array $pull, string $report, string $label, string $state): void
    {
        $body = implode("\n\n", [
            self::COMMENT_MARKER,
            '## review-diff: '.$label,
            'Reviewed `'.substr($pull['headRefOid'], 0, 7)."` against `{$pull['baseRefName']}` in a fresh session that did not write the code.",
            $report,
            '_Published by `php artisan shipkit:review`. A new push needs a new review._',
        ]);

        $file = tempnam(sys_get_temp_dir(), 'shipkit-review-');
        file_put_contents((string) $file, $body);

        try {
            $comments = $this->gh("gh api repos/{$repository}/issues/{$pull['number']}/comments --paginate --jq ".escapeshellarg('.[] | select(.body | startswith("'.self::COMMENT_MARKER.'")) | .id'), 'reading the pull request\'s comments');
            $existing = explode("\n", trim($comments))[0];

            $this->gh($existing !== ''
                ? "gh api -X PATCH repos/{$repository}/issues/comments/{$existing} -F body=@{$file}"
                : "gh api repos/{$repository}/issues/{$pull['number']}/comments -F body=@{$file}", 'posting the report');
        } finally {
            @unlink((string) $file);
        }

        $this->gh("gh api repos/{$repository}/statuses/{$pull['headRefOid']} -f state={$state} -f context=".self::STATUS_CONTEXT.' -f '.escapeshellarg("description={$label}"), 'setting the '.self::STATUS_CONTEXT.' status');
    }

    /**
     * Run a gh command, and fail with what it was doing when GitHub refuses it.
     */
    private function gh(string $command, string $doing): string
    {
        $result = Process::run($command);

        if ($result->failed()) {
            throw new RuntimeException("GitHub refused {$doing}: ".trim($result->errorOutput()));
        }

        return $result->output();
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
