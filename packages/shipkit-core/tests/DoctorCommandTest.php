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
    fakeGitHub(['gh api repos/acme/cafe/branches/staging --jq .name' => Process::result('', 'gh: Branch not found (HTTP 404)', 1)]);

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
