<?php

use ShipKit\Core\Review\CodeOwners;

it('matches files the way GitHub matches CODEOWNERS patterns', function (string $codeOwners, string $file, bool $owned) {
    expect(CodeOwners::parse($codeOwners)->owned([$file]))->toBe($owned ? [$file] : []);
})->with([
    'an anchored directory, and what is under it' => ["/packages/shipkit-core/ @ian\n", 'packages/shipkit-core/CHANGELOG.md', true],
    'an anchored directory, not elsewhere' => ["/packages/shipkit-core/ @ian\n", 'vendor/packages/shipkit-core/x.php', false],
    'an anchored file' => ["/CLAUDE.md @ian\n", 'CLAUDE.md', true],
    'an anchored file, not a deeper one' => ["/CLAUDE.md @ian\n", 'docs/CLAUDE.md', false],
    'an unanchored name, at any depth' => ["CLAUDE.md @ian\n", 'docs/CLAUDE.md', true],
    'an extension glob, at any depth' => ["*.lock @ian\n", 'packages/x/composer.lock', true],
    'a single star stays in one folder' => ["/docs/* @ian\n", 'docs/a/b.md', false],
    'a double star crosses folders' => ["/docs/**/*.md @ian\n", 'docs/a/b.md', true],
    'a slash in the middle anchors' => ["app/Models @ian\n", 'src/app/Models/User.php', false],
    'an unanchored directory, at any depth' => ["Models/ @ian\n", 'app/Models/User.php', true],
    'the last matching line wins' => ["/docs/ @ian\n/docs/guide.md\n", 'docs/guide.md', false],
    'a later line can own again' => ["/docs/guide.md\n/docs/ @ian\n", 'docs/guide.md', true],
    'comments and blank lines are ignored' => ["# owners\n\n/app/ @ian # the app\n", 'app/User.php', true],
    'an unmatched file' => ["/app/ @ian\n", 'routes/web.php', false],
]);

it('returns only the owned files, in order', function () {
    $owners = CodeOwners::parse("/.github/ @ian\n/CHANGELOG.md @ian\n");

    expect($owners->owned(['app/User.php', 'CHANGELOG.md', '.github/workflows/tests.yml']))
        ->toBe(['CHANGELOG.md', '.github/workflows/tests.yml']);
});
