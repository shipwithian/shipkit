<?php

namespace ShipKit\Core\Console;

use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use ShipKit\Core\Agents\AgentDefinitions;
use ShipKit\Core\Console\Concerns\ReadsGitHubRepository;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Checks what ShipKit's workflow relies on outside the code: the GitHub settings behind the
 * `branches` rule, and the agent definitions installed in the gitignored `.claude/agents`. Neither
 * belongs in `shipkit:verify`, which runs offline in CI. A check that cannot run (no `gh`, not
 * signed in, not on GitHub, offline) is skipped with a warning, never failed.
 *
 * `--fix` sets up what it can: it installs the agent definitions, and, after showing the changes
 * and asking, creates the integration branch, makes it GitHub's default branch, and turns on the
 * deletion of merged branches.
 */
#[Signature('shipkit:doctor
    {--fix : Set up what is missing, after confirmation}
    {--force : With --fix, change GitHub settings without asking}')]
#[Description('Check, and with --fix set up, the GitHub settings and agent definitions ShipKit\'s workflow relies on')]
class DoctorCommand extends Command
{
    use ReadsGitHubRepository;

    /**
     * What is wrong, each with the change that would fix it when the command can make it.
     *
     * @var list<array{message: string, fix: ?string, run: ?Closure(): ?string, onGitHub: bool}>
     */
    private array $problems = [];

    /**
     * @var list<string>
     */
    private array $passed = [];

    private ?string $repository = null;

    public function handle(ExecutableFinder $executables, AgentDefinitions $agents): int
    {
        $this->checkAgents($agents);
        $this->checkGitHub($executables);

        foreach ($this->passed as $line) {
            $this->components->info($line);
        }

        return $this->option('fix') ? $this->fix() : $this->report();
    }

    private function report(): int
    {
        if ($this->problems === []) {
            return self::SUCCESS;
        }

        $this->components->error('ShipKit\'s workflow is not fully set up in this project.');
        $this->components->bulletList(array_column($this->problems, 'message'));

        if (array_filter(array_column($this->problems, 'fix')) !== []) {
            $this->components->info('To set these up, run: php artisan shipkit:doctor --fix');
        }

        return self::FAILURE;
    }

    /**
     * Make every change the command knows how to make. Changes to GitHub are listed and confirmed
     * first; installing agent definitions only writes gitignored files, so it needs no question.
     */
    private function fix(): int
    {
        $fixable = array_values(array_filter($this->problems, fn (array $problem): bool => $problem['run'] !== null));

        if ($fixable === []) {
            return $this->problems === [] ? $this->nothingToFix() : $this->report();
        }

        $onGitHub = array_values(array_filter($fixable, fn (array $problem): bool => $problem['onGitHub']));

        if ($onGitHub !== [] && ! $this->confirmed($onGitHub)) {
            $fixable = array_values(array_filter($fixable, fn (array $problem): bool => ! $problem['onGitHub']));
        }

        $failed = false;

        foreach ($fixable as $problem) {
            $error = ($problem['run'])();

            if ($error === null) {
                $this->components->info("Done: {$problem['fix']}.");
            } else {
                $failed = true;
                $this->components->error("Could not: {$problem['fix']}. {$error}");
            }
        }

        $remaining = array_values(array_filter($this->problems, fn (array $problem): bool => ! in_array($problem, $fixable, true)));

        if ($remaining !== []) {
            $this->components->error('Still to do:');
            $this->components->bulletList(array_column($remaining, 'message'));
        }

        if ($failed || $remaining !== []) {
            return self::FAILURE;
        }

        if ($onGitHub !== []) {
            $this->nextSteps();
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{message: string, fix: ?string, run: ?Closure(): ?string, onGitHub: bool}>  $changes
     */
    private function confirmed(array $changes): bool
    {
        $this->components->warn("--fix will change these settings of {$this->repository} on GitHub:");
        $this->components->bulletList(array_map(fn (array $problem): string => (string) $problem['fix'], $changes));

        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Nothing on GitHub was changed. Without a prompt, pass --force to make these changes.');

            return false;
        }

        return $this->confirm("Make these changes to {$this->repository}?", false);
    }

    private function nothingToFix(): int
    {
        $this->components->info('Nothing to fix.');

        return self::SUCCESS;
    }

    private function nextSteps(): void
    {
        $branch = $this->integrationBranch();

        if ($branch === 'main') {
            return;
        }

        $this->components->info('Next:');
        $this->components->bulletList([
            "Where the app is hosted (Laravel Cloud: the environment's settings), set the production environment's branch to main, so only a release deploys.",
            "Open every pull request against `{$branch}`. It is now the default branch, so `gh pr create` targets it.",
            "Release with a pull request into main: gh pr create --base main --head {$branch} --title \"Release\"",
            $branch === 'staging' ? 'The pull request base workflow already expects `staging`.' : "Tell the pull request base workflow: gh variable set SHIPKIT_INTEGRATION_BRANCH --body {$branch}",
        ]);
    }

    private function checkAgents(AgentDefinitions $agents): void
    {
        if (! $agents->claudeCodeIsSetUp()) {
            return;
        }

        try {
            $outdated = $agents->outdated();
        } catch (InvalidArgumentException $exception) {
            $this->problem($exception->getMessage());

            return;
        }

        if ($outdated === []) {
            $this->passed[] = 'ShipKit\'s agent definitions are installed and current.';

            return;
        }

        $this->problem(
            'These agent definitions are missing or out of date in '.AgentDefinitions::TARGET_DIRECTORY.': '.implode(', ', $outdated).'. Run `php artisan shipkit:agents`; change a model or effort in shipkit.agents, not in the file.',
            'Installed ShipKit\'s agent definitions in '.AgentDefinitions::TARGET_DIRECTORY,
            function () use ($agents): ?string {
                $agents->install();

                return null;
            },
            onGitHub: false,
        );
    }

    private function checkGitHub(ExecutableFinder $executables): void
    {
        $branch = $this->integrationBranch();
        $repository = $this->repository = $this->gitHubRepository();

        if ($repository === null) {
            $this->skip('the origin remote is not on GitHub, so its branch settings were not checked.');

            return;
        }

        if ($executables->find('gh') === null) {
            $this->skip('gh is not installed, so the GitHub settings were not checked. Install it from https://cli.github.com.');

            return;
        }

        if (Process::run('gh auth status')->failed()) {
            $this->skip('gh is not signed in, so the GitHub settings were not checked. Run `gh auth login`.');

            return;
        }

        $deletesMergedBranches = Process::run("gh api repos/{$repository} --jq .delete_branch_on_merge");
        $integrationBranch = Process::run("gh api repos/{$repository}/branches/{$branch} --jq .name");
        $defaultBranch = $branch === 'main' ? null : Process::run("gh api repos/{$repository} --jq .default_branch");

        if ($deletesMergedBranches->failed() || $defaultBranch?->failed() || ($integrationBranch->failed() && ! str_contains($integrationBranch->errorOutput(), '404'))) {
            $this->skip("could not reach GitHub for {$repository}, so its settings were not checked.");

            return;
        }

        $problems = count($this->problems);

        if ($integrationBranch->failed()) {
            $this->problem(
                "The integration branch `{$branch}` does not exist on {$repository}. Push it, or set SHIPKIT_INTEGRATION_BRANCH (shipkit.workflow.integration_branch) to the branch pull requests target.",
                "Create the `{$branch}` branch from `main`",
                function () use ($repository, $branch): ?string {
                    $main = Process::run("gh api repos/{$repository}/git/ref/heads/main --jq .object.sha");

                    return $main->failed()
                        ? $this->firstLine($main->errorOutput(), 'the `main` branch was not found.')
                        : $this->change("gh api repos/{$repository}/git/refs -f ref=refs/heads/{$branch} -f sha=".trim($main->output()).' --silent');
                },
            );
        }

        if ($defaultBranch !== null && trim($defaultBranch->output()) !== $branch) {
            $this->problem(
                "GitHub's default branch is `".trim($defaultBranch->output())."`, so new pull requests and Dependabot target it instead of `{$branch}`. Make `{$branch}` the default branch in the repository settings.",
                "Make `{$branch}` the default branch",
                fn (): ?string => $this->change("gh api -X PATCH repos/{$repository} -f default_branch={$branch} --silent"),
            );
        }

        if (trim($deletesMergedBranches->output()) !== 'true') {
            $this->problem(
                "GitHub keeps merged branches, so a pull request stacked on one is never retargeted. Turn on \"Automatically delete head branches\" in the repository settings, or run: gh api -X PATCH repos/{$repository} -F delete_branch_on_merge=true",
                'Delete branches automatically after they merge',
                fn (): ?string => $this->change("gh api -X PATCH repos/{$repository} -F delete_branch_on_merge=true --silent"),
            );
        }

        if (count($this->problems) === $problems) {
            $this->passed[] = "{$repository} deletes merged branches, and pull requests target `{$branch}`".($branch === 'main' ? '.' : ', its default branch.');
        }
    }

    /**
     * Run a command that changes a GitHub setting; null on success, or why it failed.
     */
    private function change(string $command): ?string
    {
        $result = Process::run($command);

        return $result->failed() ? $this->firstLine($result->errorOutput(), 'gh reported an error.') : null;
    }

    private function firstLine(string $output, string $fallback): string
    {
        $line = trim(strtok(trim($output), "\n") ?: '');

        return $line === '' ? $fallback : $line;
    }

    /**
     * @param  (Closure(): ?string)|null  $run
     */
    private function problem(string $message, ?string $fix = null, ?Closure $run = null, bool $onGitHub = true): void
    {
        $this->problems[] = ['message' => $message, 'fix' => $fix, 'run' => $run, 'onGitHub' => $onGitHub];
    }

    private function integrationBranch(): string
    {
        return (string) config('shipkit.workflow.integration_branch', 'main');
    }

    private function skip(string $reason): void
    {
        $this->components->warn("Skipped: {$reason}");
    }
}
