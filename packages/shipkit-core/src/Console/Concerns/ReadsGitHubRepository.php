<?php

namespace ShipKit\Core\Console\Concerns;

use Illuminate\Support\Facades\Process;

trait ReadsGitHubRepository
{
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
}
