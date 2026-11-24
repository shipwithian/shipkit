<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

uses(TestCase::class);

const REVIEWED_SHA = '4a3821c0d5e1f2a3b4c5d6e7f8091a2b3c4d5e6f';

/**
 * Pretend the given programs are installed.
 *
 * @param  list<string>  $installed
 */
function programsInstalled(array $installed): void
{
    app()->instance(ExecutableFinder::class, new class($installed) extends ExecutableFinder
    {
        /**
         * @param  list<string>  $installed
         */
        public function __construct(private array $installed) {}

        public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
        {
            return in_array($name, $this->installed, true) ? "/usr/local/bin/{$name}" : $default;
        }
    });
}

/**
 * What the reviewer session prints with --output-format json and a --json-schema.
 */
function reviewerSays(string $verdict, string $report = "## Standards\n\nNo findings.\n\n## Handoff\n\nVerdict"): FakeProcessResult
{
    return Process::result((string) json_encode([
        'type' => 'result',
        'is_error' => false,
        'result' => 'Done.',
        'structured_output' => ['verdict' => $verdict, 'report' => $report],
    ]));
}

/**
 * Fake a branch with PR #12 on acme/cafe, reviewed by a session that says the given verdict.
 *
 * @param  array<string, FakeProcessResult>  $overrides
 */
function fakeReview(string $verdict = 'needs-maintainer', array $overrides = [], string $existingComment = ''): void
{
    Process::fake([
        'git status --porcelain --untracked-files=no' => Process::result(),
        'git remote get-url origin' => Process::result("git@github.com:acme/cafe.git\n"),
        'gh pr view --json number,baseRefName,headRefOid,body' => Process::result((string) json_encode([
            'number' => 12, 'baseRefName' => 'staging', 'headRefOid' => REVIEWED_SHA, 'body' => "Implements #41.\n\nCloses #42",
        ])),
        'git rev-parse HEAD' => Process::result(REVIEWED_SHA."\n"),
        "git fetch --quiet origin 'staging'" => Process::result(),
        "git diff 'origin/staging...HEAD'" => Process::result("diff --git a/app/Models/User.php b/app/Models/User.php\n+// a change\n"),
        "git diff --name-only 'origin/staging...HEAD'" => Process::result("app/Models/User.php\n"),
        "git log --oneline 'origin/staging..HEAD'" => Process::result("abc1234 change the user\n"),
        'gh pr checks 12' => Process::result("ci\tpass\n"),
        'gh issue view * --comments' => Process::result("title: the spec\n"),
        'claude -p *' => reviewerSays($verdict),
        'gh api repos/acme/cafe/issues/12/comments --paginate --jq *' => Process::result($existingComment),
        'gh api -X PATCH repos/acme/cafe/issues/comments/*' => Process::result(),
        'gh api repos/acme/cafe/issues/12/comments -F *' => Process::result(),
        'gh api repos/acme/cafe/statuses/*' => Process::result(),
        ...$overrides,
    ]);
}

beforeEach(function () {
    Process::preventStrayProcesses();
    programsInstalled(['gh', 'claude']);
});

it('publishes the review on the pull request and passes its check when only the maintainer is needed', function () {
    fakeReview('needs-maintainer');

    $this->artisan('shipkit:review')
        ->expectsOutputToContain('Needs the maintainer')
        ->assertSuccessful();

    Process::assertRan(fn ($process) => str_starts_with($process->command, 'claude -p ')
        && str_contains($process->command, 'origin/staging')
        && str_contains($process->command, '#41')
        && str_contains($process->command, '--permission-mode dontAsk')
        && str_contains($process->command, '--json-schema')
        && ! str_contains($process->command, 'Edit'));
    Process::assertRan(fn ($process) => str_starts_with($process->command, 'gh api repos/acme/cafe/issues/12/comments -F '));
    Process::assertRan(fn ($process) => $process->command === 'gh api repos/acme/cafe/statuses/'.REVIEWED_SHA." -f state=success -f context=shipkit/review -f 'description=Needs the maintainer'");
});

it('fails its check, and the command, when the work is not ready', function () {
    fakeReview('not-ready');

    $this->artisan('shipkit:review')
        ->expectsOutputToContain('Not ready')
        ->assertFailed();

    Process::assertRan(fn ($process) => $process->command === 'gh api repos/acme/cafe/statuses/'.REVIEWED_SHA." -f state=failure -f context=shipkit/review -f 'description=Not ready'");
});

it('updates its earlier comment instead of adding another', function () {
    fakeReview('agent-may-merge', existingComment: "9876\n");

    $this->artisan('shipkit:review')->assertSuccessful();

    Process::assertRan(fn ($process) => str_starts_with($process->command, 'gh api -X PATCH repos/acme/cafe/issues/comments/9876 -F '));
    Process::assertNotRan(fn ($process) => str_starts_with($process->command, 'gh api repos/acme/cafe/issues/12/comments -F '));
});

it('prints the review without publishing it on a dry run', function () {
    fakeReview('agent-may-merge');

    $this->artisan('shipkit:review --dry-run')
        ->expectsOutputToContain('No findings.')
        ->assertSuccessful();

    Process::assertNotRan(fn ($process) => str_contains($process->command, '/statuses/') || str_contains($process->command, '/comments'));
});

it('reviews against the issue it is given', function () {
    fakeReview();

    $this->artisan('shipkit:review --issue=99')->assertSuccessful();

    Process::assertRan(fn ($process) => str_starts_with($process->command, 'claude -p ') && str_contains($process->command, '#99') && ! str_contains($process->command, '#41'));
});

it('refuses to review what would not be merged', function (array $overrides, string $message) {
    fakeReview(overrides: $overrides);

    $this->artisan('shipkit:review')
        ->expectsOutputToContain($message)
        ->assertFailed();

    Process::assertNotRan(fn ($process) => str_starts_with($process->command, 'claude -p '));
})->with([
    'uncommitted changes' => [fn () => ['git status --porcelain --untracked-files=no' => Process::result(" M app/Models/User.php\n")], 'uncommitted changes'],
    'commits not pushed yet' => [fn () => ['git rev-parse HEAD' => Process::result("0000000000000000000000000000000000000000\n")], 'Push'],
    'no pull request' => [fn () => ['gh pr view --json number,baseRefName,headRefOid,body' => Process::result('', 'no pull requests found for branch "feat/x"', 1)], 'pull request'],
]);

it('says what is missing when claude or gh is not installed', function (array $installed, string $message) {
    programsInstalled($installed);
    fakeReview();

    $this->artisan('shipkit:review')
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'no claude' => [['gh'], 'claude'],
    'no gh' => [['claude'], 'gh'],
]);

it('publishes nothing when the reviewer gives no verdict', function () {
    fakeReview(overrides: ['claude -p *' => Process::result('{"type":"result","is_error":true,"result":"Rate limited."}')]);

    $this->artisan('shipkit:review')
        ->expectsOutputToContain('Rate limited.')
        ->assertFailed();

    Process::assertNotRan(fn ($process) => str_contains($process->command, '/statuses/'));
});

it('fails, and says so, when GitHub refuses the status or the comment', function (string $refused, string $message) {
    fakeReview(overrides: [$refused => Process::result('', 'HTTP 403: Resource not accessible by integration', 1)]);

    $this->artisan('shipkit:review')
        ->expectsOutputToContain("Not published: GitHub refused {$message}")
        ->assertFailed();
})->with([
    'the status' => ['gh api repos/acme/cafe/statuses/*', 'setting the shipkit/review status'],
    'the comment' => ['gh api repos/acme/cafe/issues/12/comments -F *', 'posting the report'],
]);

it('refuses a checkout whose git commands fail', function () {
    fakeReview(overrides: ['git status --porcelain --untracked-files=no' => Process::result('', 'fatal: not a git repository', 128)]);

    $this->artisan('shipkit:review')
        ->expectsOutputToContain('git status failed')
        ->assertFailed();
});

it('reviews the pull request it is given', function () {
    fakeReview(overrides: ['gh pr view 12 --json number,baseRefName,headRefOid,body' => Process::result((string) json_encode([
        'number' => 12, 'baseRefName' => 'staging', 'headRefOid' => REVIEWED_SHA, 'body' => 'Fixes #7',
    ]))]);

    $this->artisan('shipkit:review --pr=12')->assertSuccessful();

    Process::assertRan(fn ($process) => str_starts_with($process->command, 'claude -p ') && str_contains($process->command, '#7'));
});

it('reads the verdict from the result text when the session returns no structured output', function () {
    fakeReview(overrides: ['claude -p *' => Process::result((string) json_encode([
        'type' => 'result', 'is_error' => false, 'result' => (string) json_encode(['verdict' => 'not-ready', 'report' => 'Missing tests.']),
    ]))]);

    $this->artisan('shipkit:review --dry-run')
        ->expectsOutputToContain('Missing tests.')
        ->assertFailed();
});

it('lets the reviewer read but never write', function () {
    fakeReview();

    $this->artisan('shipkit:review')->assertSuccessful();

    Process::assertRan(function ($process): bool {
        if (! str_starts_with($process->command, 'claude -p ') || ! preg_match("/--allowedTools '([^']+)'/", $process->command, $tools)) {
            return false;
        }

        return preg_split('/\s+/', $tools[1]) === ['Read', 'Glob', 'Grep', 'Agent', 'Skill'];
    });
});

it('hands the reviewers the diff, commits, spec, and CI as files, so they need no shell, and clears them after', function () {
    fakeReview();
    $material = storage_path('framework/cache/shipkit-review');

    $this->artisan('shipkit:review')->assertSuccessful();

    Process::assertRan(fn ($process) => str_starts_with($process->command, 'claude -p ')
        && str_contains($process->command, 'storage/framework/cache/shipkit-review/diff.patch')
        && str_contains($process->command, 'storage/framework/cache/shipkit-review/spec.md')
        && str_contains($process->command, 'shipkit-standards-reviewer')
        && str_contains($process->command, 'shipkit-spec-reviewer')
        && str_contains($process->command, 'do not base the verdict on CI that is still running')
        && str_contains($process->command, "--allowedTools 'Read Glob Grep Agent Skill'"));

    expect(is_dir($material))->toBeFalse();
});

it('refuses a pull request with nothing to review', function () {
    fakeReview(overrides: ["git diff 'origin/staging...HEAD'" => Process::result('')]);

    $this->artisan('shipkit:review')
        ->expectsOutputToContain('nothing to review')
        ->assertFailed();

    Process::assertNotRan(fn ($process) => str_starts_with($process->command, 'claude -p '));
});
