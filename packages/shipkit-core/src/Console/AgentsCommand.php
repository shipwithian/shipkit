<?php

namespace ShipKit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use ShipKit\Core\Agents\AgentDefinitions;

#[Signature('shipkit:agents')]
#[Description("Install ShipKit's Claude Code agent definitions, which pin each sub-agent's model and effort")]
class AgentsCommand extends Command
{
    public function handle(AgentDefinitions $agents): int
    {
        if (! $agents->claudeCodeIsSetUp()) {
            $this->components->info('boost.json does not set up Claude Code, so no agent definitions were installed.');

            return self::SUCCESS;
        }

        try {
            $installed = $agents->install();
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Installed '.count($installed).' agent definitions in '.AgentDefinitions::TARGET_DIRECTORY.'.');

        return self::SUCCESS;
    }
}
