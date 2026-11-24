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
     * What the reviewer session may do: read the code, the history, the spec, and the checks, and
     * spawn the two reviewers. Nothing that edits, commits, pushes, or comments.
     */
    public const string ALLOWED_TOOLS = 'Read Glob Grep Agent Skill Bash(git diff:*) Bash(git log:*) Bash(git show:*) Bash(git rev-parse:*) Bash(gh issue view:*) Bash(gh pr view:*) Bash(gh pr diff:*) Bash(gh pr checks:*)';

    public function handle(ExecutableFinder $executables): int
    {
        try {
            foreach (['gh' => 'https://cli.github.com', 'claude' => 'https://code.claude.com'] as $program => $site) {
                if ($executables->find($program) === null) {
                    throw new RuntimeException("{$program} is not installed; shipkit:review needs it. Install it from {$site}.");
                }
            }

            $repository = $this->gitHubRepository() ?? throw new RuntimeException('The origin remote is not on GitHub, so there is no pull request to review.');

            if (trim(Process::run('git status --porcelain --untracked-files=no')->output()) !== '') {
                throw new RuntimeException('The working tree has uncommitted changes. Commit and push them first: the review covers what the pull request would merge.');
            }

            $pull = $this->pullRequest();

            if (trim(Process::run('git rev-parse HEAD')->output()) !== $pull['headRefOid']) {
                throw new RuntimeException("This checkout is not at pull request #{$pull['number']}'s latest commit. Push your commits, or pull the branch, so the review covers what would merge.");
            }

            $issue = $this->stringOption('issue') ?? $this->specIssue($pull['body']);
            $review = $this->review($pull['baseRefName'], $issue);
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

        $this->publish($repository, $pull, $review['report'], $label, $state);

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
        $view = Process::run('gh pr view'.($number === null ? '' : ' '.escapeshellarg($number)).' --json number,baseRefName,headRefOid,body');
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
     * @return array{verdict: string, report: string}
     */
    private function review(string $base, ?string $issue): array
    {
        $spec = $issue === null
            ? 'There is no spec issue; say so on the Spec axis.'
            : "The spec is GitHub issue #{$issue}; read it with `gh issue view {$issue} --comments`.";

        $prompt = implode(' ', [
            "Run the review-diff skill on this branch, with fixed point origin/{$base}.",
            $spec,
            'You are the independent review: you did not write this code.',
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
            $existing = trim(strtok(Process::run("gh api repos/{$repository}/issues/{$pull['number']}/comments --paginate --jq ".escapeshellarg('.[] | select(.body | startswith("'.self::COMMENT_MARKER.'")) | .id'))->output(), "\n") ?: '');

            Process::run($existing !== ''
                ? "gh api -X PATCH repos/{$repository}/issues/comments/{$existing} -F body=@{$file}"
                : "gh api repos/{$repository}/issues/{$pull['number']}/comments -F body=@{$file}");
        } finally {
            @unlink((string) $file);
        }

        Process::run("gh api repos/{$repository}/statuses/{$pull['headRefOid']} -f state={$state} -f context=".self::STATUS_CONTEXT.' -f '.escapeshellarg("description={$label}"));
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
