<?php

use Symfony\Component\Process\Process;

/**
 * Tracked files that the installer archive deliberately leaves out, with the reason.
 *
 * `.gitattributes` patterns without a leading slash match in every folder, so an unanchored
 * `README.md` rule would also drop `.ai/skills/verify-app/features/README.md` from new projects.
 *
 * @var array<string, string>
 */
const ARCHIVE_EXCLUDED = [
    'README.md' => 'the template README; a new project writes its own',
    'CHANGELOG.md' => 'the template changelog; a new project starts its own',
    '.github/workflows/browser-tests.yml' => 'a workflow for the template itself',
    '.github/workflows/fresh-project.yml' => 'a workflow for the template itself',
    '.github/scripts/fresh-project.sh' => 'the script behind the template\'s fresh-project workflow',
    '.github/CODEOWNERS' => 'names the template\'s maintainer; a new project names its own',
    'docs/branch-protection.md' => 'the template maintainer\'s runbook for protecting its main branch',
];

it('ships every tracked file in the installer archive except the documented few', function () {
    if (! file_exists(base_path('.git'))) {
        $this->markTestSkipped('This is not a git checkout, so there are no archive attributes to read.');
    }

    $tracked = new Process(['git', 'ls-files'], base_path());
    $tracked->mustRun();
    $files = array_values(array_filter(explode("\n", $tracked->getOutput())));

    $attributes = new Process(['git', 'check-attr', 'export-ignore', '--', ...$files], base_path());
    $attributes->mustRun();

    $dropped = [];

    foreach (explode("\n", $attributes->getOutput()) as $line) {
        if (preg_match('/^(.+): export-ignore: set$/', $line, $match) && ! array_key_exists($match[1], ARCHIVE_EXCLUDED)) {
            $dropped[] = "{$match[1]} is left out of the installer archive by .gitattributes, so new projects will not receive it: anchor the pattern with a leading slash, or list the file in ARCHIVE_EXCLUDED with the reason";
        }
    }

    expect($dropped)->toBeEmpty(implode(PHP_EOL, $dropped));
});
