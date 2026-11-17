<?php

namespace ShipKit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use ShipKit\Core\Agents\AgentDefinitions;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Checks what ShipKit's workflow relies on outside the code: the GitHub settings behind the
 * `branches` rule, and the agent definitions installed in the gitignored `.claude/agents`. Neither
 * belongs in `shipkit:verify`, which runs offline in CI. A check that cannot run (no `gh`, not
 * signed in, not on GitHub, offline) is skipped with a warning, never failed.
 */
#[Signature('shipkit:doctor')]
#[Description('Check the GitHub settings and agent definitions ShipKit\'s workflow relies on')]
class DoctorCommand extends Command
{
    /**
     * @var list<string>
     */
    private array $problems = [];

    /**
     * @var list<string>
     */
    private array $passed = [];

    public function handle(ExecutableFinder $executables, AgentDefinitions $agents): int
    {
        $this->checkAgents($agents);
        $this->checkGitHub($executables);

        foreach ($this->passed as $line) {
            $this->components->info($line);
        }

        if ($this->problems !== []) {
            $this->components->error('ShipKit\'s workflow is not fully set up in this project.');
            $this->components->bulletList($this->problems);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function checkAgents(AgentDefinitions $agents): void
    {
        if (! $agents->claudeCodeIsSetUp()) {
            return;
        }

        try {
            $outdated = $agents->outdated();
        } catch (InvalidArgumentException $exception) {
            $this->problems[] = $exception->getMessage();

            return;
        }

        if ($outdated === []) {
            $this->passed[] = 'ShipKit\'s agent definitions are installed and current.';

            return;
        }

        $this->problems[] = 'These agent definitions are missing or out of date in '.AgentDefinitions::TARGET_DIRECTORY.': '.implode(', ', $outdated).'. Run `php artisan shipkit:agents`; change a model or effort in shipkit.agents, not in the file.';
    }

    private function checkGitHub(ExecutableFinder $executables): void
    {
        $branch = (string) config('shipkit.workflow.integration_branch', 'main');
        $repository = $this->gitHubRepository();

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

        if ($deletesMergedBranches->failed() || ($integrationBranch->failed() && ! str_contains($integrationBranch->errorOutput(), '404'))) {
            $this->skip("could not reach GitHub for {$repository}, so its settings were not checked.");

            return;
        }

        $problems = count($this->problems);

        if (trim($deletesMergedBranches->output()) !== 'true') {
            $this->problems[] = "GitHub keeps merged branches, so a pull request stacked on one is never retargeted. Turn on \"Automatically delete head branches\" in the repository settings, or run: gh api -X PATCH repos/{$repository} -F delete_branch_on_merge=true";
        }

        if ($integrationBranch->failed()) {
            $this->problems[] = "The integration branch `{$branch}` does not exist on {$repository}. Push it, or set SHIPKIT_INTEGRATION_BRANCH (shipkit.workflow.integration_branch) to the branch pull requests target.";
        }

        if (count($this->problems) === $problems) {
            $this->passed[] = "{$repository} deletes merged branches, and pull requests target `{$branch}`.";
        }
    }

    /**
     * The `owner/name` of the origin remote when it is on GitHub.
     */
    private function gitHubRepository(): ?string
    {
        $remote = Process::run('git remote get-url origin');

        if ($remote->failed() || ! preg_match('#github\.com[:/]([\w.-]+/[\w.-]+?)(?:\.git)?/?$#', trim($remote->output()), $match)) {
            return null;
        }

        return $match[1];
    }

    private function skip(string $reason): void
    {
        $this->components->warn("Skipped: {$reason}");
    }
}
