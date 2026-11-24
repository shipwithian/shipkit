<?php

namespace ShipKit\Core\Review;

/**
 * A repository's CODEOWNERS file, matched the way GitHub matches it: each line is a gitignore-style
 * pattern followed by its owners, the last matching line decides a file's owners, and a line with
 * no owners leaves the files it matches unowned. Under ShipKit's merge rule an owned file is
 * maintainer-owned, so an agent may not merge a change to it.
 */
class CodeOwners
{
    /**
     * Where GitHub looks for the file, in the order it looks.
     */
    public const array LOCATIONS = ['.github/CODEOWNERS', 'CODEOWNERS', 'docs/CODEOWNERS'];

    /**
     * @param  list<array{pattern: string, owned: bool}>  $rules
     */
    private function __construct(private array $rules) {}

    public static function parse(string $contents): self
    {
        $rules = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim((string) preg_replace('/(?<!\\\\)#.*$/', '', $line));

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $line) ?: [];
            $rules[] = ['pattern' => self::toRegex((string) array_shift($parts)), 'owned' => $parts !== []];
        }

        return new self($rules);
    }

    /**
     * The given files that have an owner.
     *
     * @param  list<string>  $files  paths relative to the repository root
     * @return list<string>
     */
    public function owned(array $files): array
    {
        return array_values(array_filter($files, fn (string $file): bool => $this->isOwned($file)));
    }

    private function isOwned(string $file): bool
    {
        $owned = false;

        foreach ($this->rules as $rule) {
            if (preg_match($rule['pattern'], ltrim($file, '/')) === 1) {
                $owned = $rule['owned'];
            }
        }

        return $owned;
    }

    /**
     * A gitignore-style pattern as a regular expression. A leading slash, or a slash before the
     * last character, anchors it to the root; otherwise it matches at any depth. A pattern that
     * names a directory also matches everything under it, except one ending in a single `*`, which
     * GitHub limits to that folder's own files (`docs/*` does not match `docs/a/b.md`).
     */
    private static function toRegex(string $pattern): string
    {
        $anchored = str_starts_with($pattern, '/') || str_contains(rtrim($pattern, '/'), '/');
        $directoryOnly = str_ends_with($pattern, '/');
        $pattern = trim($pattern, '/');
        $endsInOneFolder = str_ends_with($pattern, '*') && ! str_ends_with($pattern, '**');

        $regex = '';

        foreach (preg_split('/(\*\*\/|\/\*\*$|\*\*|\*|\?)/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $regex .= match ($token) {
                '**/' => '(?:.*/)?',
                '/**' => '/.*',
                '**' => '.*',
                '*' => '[^/]*',
                '?' => '[^/]',
                default => preg_quote($token, '#'),
            };
        }

        return '#^'.($anchored ? '' : '(?:.*/)?').$regex.match (true) {
            $directoryOnly => '/.*',
            $endsInOneFolder => '',
            default => '(?:/.*)?',
        }.'$#';
    }
}
