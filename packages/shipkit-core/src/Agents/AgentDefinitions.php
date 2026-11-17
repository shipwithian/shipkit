<?php

namespace ShipKit\Core\Agents;

use InvalidArgumentException;

/**
 * ShipKit's Claude Code agent definitions, which pin each sub-agent's model and effort in their
 * frontmatter so a skill that spawns one never inherits the parent's model. Laravel Boost syncs
 * skills and guidelines but not agent definitions, so `shipkit:agents` installs these into
 * `.claude/agents`, with a project's overrides from `shipkit.agents` applied.
 */
class AgentDefinitions
{
    public const string SOURCE_DIRECTORY = __DIR__.'/../../resources/claude/agents';

    public const string TARGET_DIRECTORY = '.claude/agents';

    /**
     * The effort levels an override may set. `max` is deliberately missing: the subagents guideline
     * never allows it.
     */
    public const array EFFORT_LEVELS = ['low', 'medium', 'high', 'xhigh'];

    /**
     * The model aliases an override may set, besides a full model id such as `claude-sonnet-5-5`.
     */
    public const array MODEL_ALIASES = ['haiku', 'sonnet', 'opus', 'fable', 'inherit'];

    /**
     * Whether the project's boost.json sets up Claude Code, the only client that reads these.
     */
    public function claudeCodeIsSetUp(): bool
    {
        $path = base_path('boost.json');
        $boost = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($boost) && in_array('claude_code', (array) ($boost['agents'] ?? []), true);
    }

    /**
     * Every definition as it should be installed, keyed by file name.
     *
     * @return array<string, string>
     *
     * @throws InvalidArgumentException when an override names an agent ShipKit does not ship
     */
    public function rendered(): array
    {
        /** @var array<string, array<string, mixed>> $overrides Config, so validated below rather than trusted. */
        $overrides = (array) config('shipkit.agents', []);
        $definitions = [];

        foreach (glob(self::SOURCE_DIRECTORY.'/*.md') ?: [] as $path) {
            $definitions[basename($path)] = (string) file_get_contents($path);
        }

        foreach ($overrides as $agent => $fields) {
            $file = "{$agent}.md";

            if (! isset($definitions[$file])) {
                throw new InvalidArgumentException("shipkit.agents names `{$agent}`, which ShipKit does not ship. Its agents are: ".implode(', ', array_map(fn (string $name): string => basename($name, '.md'), array_keys($definitions))).'.');
            }

            $model = $fields['model'] ?? null;
            $effort = $fields['effort'] ?? null;

            if ($model !== null && (! is_string($model) || (! in_array($model, self::MODEL_ALIASES, true) && ! preg_match('/^claude-[a-z0-9.-]+$/', $model)))) {
                throw new InvalidArgumentException("shipkit.agents.{$agent}.model must be one of ".implode(', ', self::MODEL_ALIASES).', or a full model id such as claude-sonnet-5-5.');
            }

            if ($effort !== null && (! is_string($effort) || ! in_array($effort, self::EFFORT_LEVELS, true))) {
                throw new InvalidArgumentException("shipkit.agents.{$agent}.effort must be one of ".implode(', ', self::EFFORT_LEVELS).'; ShipKit never runs an agent at max.');
            }

            foreach (array_intersect_key($fields, ['model' => true, 'effort' => true]) as $field => $value) {
                $definitions[$file] = (string) preg_replace("/^{$field}: .*$/m", "{$field}: ".(string) $value, $definitions[$file], 1);
            }
        }

        ksort($definitions);

        return $definitions;
    }

    /**
     * Write every definition into `.claude/agents`, leaving the project's own agents alone.
     *
     * @return list<string> the files written
     */
    public function install(): array
    {
        $directory = base_path(self::TARGET_DIRECTORY);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $definitions = $this->rendered();

        foreach ($definitions as $file => $contents) {
            file_put_contents("{$directory}/{$file}", $contents);
        }

        return array_keys($definitions);
    }

    /**
     * The installed definitions that are missing or differ from what `install()` would write.
     *
     * @return list<string>
     */
    public function outdated(): array
    {
        $outdated = [];

        foreach ($this->rendered() as $file => $contents) {
            $path = base_path(self::TARGET_DIRECTORY."/{$file}");

            if (! is_file($path) || file_get_contents($path) !== $contents) {
                $outdated[] = $file;
            }
        }

        return $outdated;
    }
}
