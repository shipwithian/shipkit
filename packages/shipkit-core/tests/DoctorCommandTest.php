<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

uses(TestCase::class);

/**
 * Pretend `gh` is installed or not.
 */
function ghInstalled(bool $installed): void
{
    app()->instance(ExecutableFinder::class, new class($installed) extends ExecutableFinder
    {
        public function __construct(private bool $installed) {}

        public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
        {
            return $name === 'gh' && $this->installed ? '/usr/local/bin/gh' : $default;
        }
    });
}

/**
 * Fake the git and gh calls of a project on GitHub, overriding any of them.
 *
 * @param  array<string, FakeProcessResult>  $overrides
 */
function fakeGitHub(array $overrides = []): void
{
    Process::fake([
        'git remote get-url origin' => Process::result("git@github.com:acme/cafe.git\n"),
        'gh auth status' => Process::result(),
        'gh api repos/acme/cafe --jq .delete_branch_on_merge' => Process::result("true\n"),
        'gh api repos/acme/cafe/branches/main --jq .name' => Process::result("main\n"),
        ...$overrides,
    ]);
}

/**
 * Point the application at a scratch project whose boost.json lists the given agents.
 *
 * @param  list<string>  $boostAgents
 */
function doctorInScratchProject(array $boostAgents): string
{
    $basePath = sys_get_temp_dir().'/shipkit-doctor-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($basePath);
    File::put("{$basePath}/boost.json", (string) json_encode(['agents' => $boostAgents]));
    app()->setBasePath($basePath);

    return $basePath;
}

afterEach(function () {
    if (str_starts_with(base_path(), sys_get_temp_dir().'/shipkit-doctor-')) {
        File::deleteDirectory(base_path());
    }
});

beforeEach(function () {
    Process::preventStrayProcesses();
    doctorInScratchProject(['codex']);
    ghInstalled(true);
    config(['shipkit.workflow.integration_branch' => 'main']);
});

it('passes when GitHub deletes merged branches and the integration branch exists', function () {
    fakeGitHub();

    $this->artisan('shipkit:doctor')->assertSuccessful();
});

it('reads an https remote', function () {
    fakeGitHub(['git remote get-url origin' => Process::result("https://github.com/acme/cafe.git\n")]);

    $this->artisan('shipkit:doctor')->assertSuccessful();
});

it('fails with the fix when GitHub keeps merged branches', function () {
    fakeGitHub(['gh api repos/acme/cafe --jq .delete_branch_on_merge' => Process::result("false\n")]);

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('gh api -X PATCH repos/acme/cafe -F delete_branch_on_merge=true')
        ->assertFailed();
});

it('fails when the integration branch does not exist on GitHub', function () {
    config(['shipkit.workflow.integration_branch' => 'staging']);
    fakeGitHub([
        'gh api repos/acme/cafe/branches/staging --jq .name' => Process::result('', 'gh: Branch not found (HTTP 404)', 1),
        'gh api repos/acme/cafe --jq .default_branch' => Process::result("staging\n"),
    ]);

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('staging')
        ->assertFailed();
});

it('skips the GitHub checks when gh is not installed', function () {
    ghInstalled(false);
    fakeGitHub();

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('gh is not installed')
        ->assertSuccessful();
});

it('skips the GitHub checks when gh is not signed in', function () {
    fakeGitHub(['gh auth status' => Process::result('', 'You are not logged into any GitHub hosts.', 1)]);

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('gh auth login')
        ->assertSuccessful();
});

it('skips the GitHub checks when the remote is not on GitHub', function () {
    fakeGitHub(['git remote get-url origin' => Process::result("git@gitlab.com:acme/cafe.git\n")]);

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('not on GitHub')
        ->assertSuccessful();

    Process::assertNotRan('gh auth status');
});

it('skips the GitHub checks without an origin remote', function () {
    fakeGitHub(['git remote get-url origin' => Process::result('', "error: No such remote 'origin'", 2)]);

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('not on GitHub')
        ->assertSuccessful();
});

it('skips instead of failing when GitHub cannot be reached', function () {
    fakeGitHub(['gh api repos/acme/cafe --jq .delete_branch_on_merge' => Process::result('', 'error connecting to api.github.com', 1)]);

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('could not reach GitHub')
        ->assertSuccessful();
});

it('fails when the agent definitions are missing for Claude Code', function () {
    doctorInScratchProject(['claude_code']);
    fakeGitHub();

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('php artisan shipkit:agents')
        ->assertFailed();
});

it('passes when the agent definitions are installed and current', function () {
    doctorInScratchProject(['claude_code']);
    fakeGitHub();
    $this->artisan('shipkit:agents')->assertSuccessful();

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('agent definitions are installed and current')
        ->assertSuccessful();
});

it('still checks the agent definitions when GitHub is skipped', function () {
    doctorInScratchProject(['claude_code']);
    ghInstalled(false);
    fakeGitHub();

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('gh is not installed')
        ->expectsOutputToContain('php artisan shipkit:agents')
        ->assertFailed();
});

/**
 * Fake a project on GitHub that works on `staging`, with every setting in place unless overridden.
 *
 * @param  array<string, FakeProcessResult>  $overrides
 */
function fakeGitHubOnStaging(array $overrides = []): void
{
    config(['shipkit.workflow.integration_branch' => 'staging']);

    fakeGitHub([
        'gh api repos/acme/cafe/branches/staging --jq .name' => Process::result("staging\n"),
        'gh api repos/acme/cafe --jq .default_branch' => Process::result("staging\n"),
        ...$overrides,
    ]);
}

/**
 * A repository as GitHub creates it: only `main`, which is the default, and merged branches kept.
 *
 * @param  array<string, FakeProcessResult>  $overrides
 */
function fakeFreshGitHubRepository(array $overrides = []): void
{
    fakeGitHubOnStaging([
        'gh api repos/acme/cafe/branches/staging --jq .name' => Process::result('', 'gh: Branch not found (HTTP 404)', 1),
        'gh api repos/acme/cafe --jq .default_branch' => Process::result("main\n"),
        'gh api repos/acme/cafe --jq .delete_branch_on_merge' => Process::result("false\n"),
        'gh api repos/acme/cafe/git/ref/heads/main --jq .object.sha' => Process::result("abc123\n"),
        'gh api repos/acme/cafe/git/refs -f ref=refs/heads/staging -f sha=abc123 --silent' => Process::result(),
        'gh api -X PATCH repos/acme/cafe -f default_branch=staging --silent' => Process::result(),
        'gh api -X PATCH repos/acme/cafe -F delete_branch_on_merge=true --silent' => Process::result(),
        ...$overrides,
    ]);
}

const FIX_COMMANDS = [
    'gh api repos/acme/cafe/git/refs -f ref=refs/heads/staging -f sha=abc123 --silent',
    'gh api -X PATCH repos/acme/cafe -f default_branch=staging --silent',
    'gh api -X PATCH repos/acme/cafe -F delete_branch_on_merge=true --silent',
];

it('passes for a project on staging when it is the default branch', function () {
    fakeGitHubOnStaging();

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('pull requests target `staging`')
        ->assertSuccessful();
});

it('fails when the integration branch is not GitHub\'s default branch', function () {
    fakeGitHubOnStaging(['gh api repos/acme/cafe --jq .default_branch' => Process::result("main\n")]);

    $this->artisan('shipkit:doctor')
        ->expectsOutputToContain('default branch is `main`')
        ->expectsOutputToContain('php artisan shipkit:doctor --fix')
        ->assertFailed();
});

it('does not ask for the default branch of a project that works on main', function () {
    fakeGitHub();

    $this->artisan('shipkit:doctor')->assertSuccessful();

    Process::assertNotRan('gh api repos/acme/cafe --jq .default_branch');
});

it('sets up a fresh repository with --fix, after confirmation', function () {
    fakeFreshGitHubRepository();

    $this->artisan('shipkit:doctor --fix')
        ->expectsOutputToContain('Create the `staging` branch from `main`')
        ->expectsOutputToContain('Make `staging` the default branch')
        ->expectsOutputToContain('Delete branches automatically after they merge')
        ->expectsConfirmation('Make these changes to acme/cafe?', 'yes')
        ->expectsOutputToContain('set the production environment\'s branch to main')
        ->assertSuccessful();

    foreach (FIX_COMMANDS as $command) {
        Process::assertRan($command);
    }
});

it('changes nothing when the confirmation is declined', function () {
    fakeFreshGitHubRepository();

    $this->artisan('shipkit:doctor --fix')
        ->expectsConfirmation('Make these changes to acme/cafe?', 'no')
        ->assertFailed();

    foreach (FIX_COMMANDS as $command) {
        Process::assertNotRan($command);
    }
});

it('needs --force to fix without a prompt', function () {
    fakeFreshGitHubRepository();

    $this->artisan('shipkit:doctor --fix --no-interaction')
        ->expectsOutputToContain('--force')
        ->assertFailed();

    foreach (FIX_COMMANDS as $command) {
        Process::assertNotRan($command);
    }

    $this->artisan('shipkit:doctor --fix --force --no-interaction')->assertSuccessful();

    foreach (FIX_COMMANDS as $command) {
        Process::assertRan($command);
    }
});

it('fixes only what is wrong', function () {
    fakeGitHubOnStaging(['gh api repos/acme/cafe --jq .default_branch' => Process::result("main\n"),
        'gh api -X PATCH repos/acme/cafe -f default_branch=staging --silent' => Process::result()]);

    $this->artisan('shipkit:doctor --fix')
        ->expectsConfirmation('Make these changes to acme/cafe?', 'yes')
        ->assertSuccessful();

    Process::assertRan('gh api -X PATCH repos/acme/cafe -f default_branch=staging --silent');
    Process::assertNotRan('gh api -X PATCH repos/acme/cafe -F delete_branch_on_merge=true --silent');
});

it('reports a change GitHub refuses and stays failed', function () {
    fakeFreshGitHubRepository(['gh api -X PATCH repos/acme/cafe -f default_branch=staging --silent' => Process::result('', 'gh: Must have admin rights to Repository. (HTTP 403)', 1)]);

    $this->artisan('shipkit:doctor --fix')
        ->expectsConfirmation('Make these changes to acme/cafe?', 'yes')
        ->expectsOutputToContain('Must have admin rights')
        ->assertFailed();

    Process::assertRan('gh api -X PATCH repos/acme/cafe -F delete_branch_on_merge=true --silent');
});

it('says so when --fix finds nothing to change', function () {
    fakeGitHubOnStaging();

    $this->artisan('shipkit:doctor --fix')
        ->expectsOutputToContain('Nothing to fix')
        ->assertSuccessful();
});

it('installs the agent definitions with --fix, without asking, even when GitHub is skipped', function () {
    $basePath = doctorInScratchProject(['claude_code']);
    ghInstalled(false);
    fakeGitHub();

    $this->artisan('shipkit:doctor --fix')
        ->expectsOutputToContain('Installed ShipKit\'s agent definitions')
        ->assertSuccessful();

    expect(File::exists("{$basePath}/.claude/agents/shipkit-explorer.md"))->toBeTrue();
});
