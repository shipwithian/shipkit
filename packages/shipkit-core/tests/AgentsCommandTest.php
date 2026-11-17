<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;
use ShipKit\Core\Agents\AgentDefinitions;

uses(TestCase::class);

const SHIPPED_AGENTS = ['shipkit-explorer.md', 'shipkit-spec-reviewer.md', 'shipkit-standards-reviewer.md'];

afterEach(function () {
    if (str_starts_with(base_path(), sys_get_temp_dir().'/shipkit-agents-')) {
        File::deleteDirectory(base_path());
    }
});

/**
 * Point the application at a scratch project whose boost.json lists the given agents.
 *
 * @param  list<string>|null  $boostAgents  null for a project without boost.json
 */
function agentsInScratchProject(?array $boostAgents): string
{
    $basePath = sys_get_temp_dir().'/shipkit-agents-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($basePath);

    if ($boostAgents !== null) {
        File::put("{$basePath}/boost.json", (string) json_encode(['agents' => $boostAgents]));
    }

    app()->setBasePath($basePath);

    return $basePath;
}

it('installs ShipKit\'s agent definitions for Claude Code', function () {
    $basePath = agentsInScratchProject(['codex', 'claude_code']);

    $this->artisan('shipkit:agents')->assertSuccessful();

    expect(array_map(basename(...), File::files("{$basePath}/.claude/agents")))->toEqualCanonicalizing(SHIPPED_AGENTS)
        ->and(File::get("{$basePath}/.claude/agents/shipkit-explorer.md"))->toContain("model: haiku\neffort: medium\n");
});

it('applies a project\'s override of an agent\'s model and effort', function () {
    $basePath = agentsInScratchProject(['claude_code']);
    config(['shipkit.agents' => ['shipkit-standards-reviewer' => ['model' => 'sonnet', 'effort' => 'high']]]);

    $this->artisan('shipkit:agents')->assertSuccessful();

    expect(File::get("{$basePath}/.claude/agents/shipkit-standards-reviewer.md"))->toContain("model: sonnet\neffort: high\n")
        ->and(File::get("{$basePath}/.claude/agents/shipkit-spec-reviewer.md"))->toContain("model: opus\neffort: low\n");
});

it('overrides only the field a project sets', function () {
    $basePath = agentsInScratchProject(['claude_code']);
    config(['shipkit.agents' => ['shipkit-explorer' => ['effort' => 'high']]]);

    $this->artisan('shipkit:agents')->assertSuccessful();

    expect(File::get("{$basePath}/.claude/agents/shipkit-explorer.md"))->toContain("model: haiku\neffort: high\n");
});

it('fails on an override for an agent ShipKit does not ship', function () {
    agentsInScratchProject(['claude_code']);
    config(['shipkit.agents' => ['shipkit-reviewer' => ['model' => 'sonnet']]]);

    $this->artisan('shipkit:agents')
        ->expectsOutputToContain('shipkit-reviewer')
        ->assertFailed();
});

it('fails on an override outside the allowed models and effort levels', function (array $override, string $message) {
    agentsInScratchProject(['claude_code']);
    config(['shipkit.agents' => ['shipkit-explorer' => $override]]);

    $this->artisan('shipkit:agents')
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'max effort' => [['effort' => 'max'], 'never runs an agent at max'],
    'an unknown effort' => [['effort' => 'extreme'], 'shipkit.agents.shipkit-explorer.effort'],
    'an unknown model' => [['model' => 'gpt-5'], 'shipkit.agents.shipkit-explorer.model'],
]);

it('accepts a full model id as an override', function () {
    $basePath = agentsInScratchProject(['claude_code']);
    config(['shipkit.agents' => ['shipkit-explorer' => ['model' => 'claude-sonnet-5-5']]]);

    $this->artisan('shipkit:agents')->assertSuccessful();

    expect(File::get("{$basePath}/.claude/agents/shipkit-explorer.md"))->toContain("model: claude-sonnet-5-5\n");
});

it('writes the same files when run again', function () {
    $basePath = agentsInScratchProject(['claude_code']);

    $this->artisan('shipkit:agents')->assertSuccessful();
    $first = File::get("{$basePath}/.claude/agents/shipkit-spec-reviewer.md");
    $this->artisan('shipkit:agents')->assertSuccessful();

    expect(File::get("{$basePath}/.claude/agents/shipkit-spec-reviewer.md"))->toBe($first);
});

it('leaves the project\'s own agents alone', function () {
    $basePath = agentsInScratchProject(['claude_code']);
    File::ensureDirectoryExists("{$basePath}/.claude/agents");
    File::put("{$basePath}/.claude/agents/menu-writer.md", "---\nname: menu-writer\n---\nMine.\n");

    $this->artisan('shipkit:agents')->assertSuccessful();

    expect(File::get("{$basePath}/.claude/agents/menu-writer.md"))->toBe("---\nname: menu-writer\n---\nMine.\n");
});

it('does nothing in a project that does not use Claude Code', function (?array $boostAgents) {
    $basePath = agentsInScratchProject($boostAgents);

    $this->artisan('shipkit:agents')->assertSuccessful();

    expect(File::isDirectory("{$basePath}/.claude/agents"))->toBeFalse();
})->with([
    'another client' => [['codex']],
    'no boost.json' => [null],
]);

it('reports missing and outdated definitions to the doctor', function () {
    $basePath = agentsInScratchProject(['claude_code']);
    $this->artisan('shipkit:agents')->assertSuccessful();

    File::put("{$basePath}/.claude/agents/shipkit-explorer.md", 'changed by hand');
    File::delete("{$basePath}/.claude/agents/shipkit-spec-reviewer.md");

    expect(app(AgentDefinitions::class)->outdated())->toEqualCanonicalizing(['shipkit-explorer.md', 'shipkit-spec-reviewer.md']);
});
