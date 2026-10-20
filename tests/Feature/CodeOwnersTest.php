<?php

use Illuminate\Support\Str;

/**
 * GitHub silently ignores a CODEOWNERS pattern that matches nothing, so a renamed or moved check
 * would lose its required review without anyone noticing.
 */
it('names only paths that exist, each with an owner', function () {
    $codeOwners = base_path('.github/CODEOWNERS');

    if (! is_file($codeOwners)) {
        $this->markTestSkipped('This project has no CODEOWNERS file.');
    }

    $problems = [];

    foreach (file($codeOwners, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $fields = preg_split('/\s+/', $line) ?: [];
        $pattern = $fields[0];
        $lineNumber = $number + 1;

        if (count($fields) < 2) {
            $problems[] = "Line {$lineNumber}: `{$pattern}` has no owner, so it removes the required review instead of adding one.";
        }

        if (! str_starts_with($pattern, '/') || Str::contains($pattern, ['*', '?', '['])) {
            $problems[] = "Line {$lineNumber}: `{$pattern}` is not an anchored path; name the exact file or folder from the repository root.";

            continue;
        }

        if (! file_exists(base_path(ltrim($pattern, '/')))) {
            $problems[] = "Line {$lineNumber}: `{$pattern}` does not exist, so nothing it should protect needs review; update the path.";
        }
    }

    expect($problems)->toBeEmpty(implode(PHP_EOL, $problems));
});
