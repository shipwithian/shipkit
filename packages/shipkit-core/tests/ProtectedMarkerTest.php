<?php

use Illuminate\Support\Str;
use ShipKit\Core\AccessControl\BootstrapAccessControlAction;
use ShipKit\Core\Architecture\ProjectLayout;

it('writes the protected marker only through the access-control bootstrap', function () {
    $layout = ProjectLayout::current();
    $writes = 'forceFill|fill|create|forceCreate|createQuietly|forceCreateQuietly|update|make|firstOrCreate|firstOrNew|updateOrCreate|incrementOrCreate|createOrFirst|insert|insertGetId|insertOrIgnore|upsert|updateOrInsert|state';
    $patterns = [
        "/\\b(?:{$writes})\\([^;]*?['\"]is_protected['\"]\\s*=>/",
        "/\\bnew\\s+\\\\?[A-Z][\\w\\\\]*\\([^;]*?['\"]is_protected['\"]\\s*=>/",
        "/->setAttribute\\(\\s*['\"]is_protected['\"]/",
        '/->is_protected\s*=(?!=)/',
    ];
    /** @var array<string, string> $exceptions */
    $exceptions = (array) $layout->config('architecture.protected_marker_exceptions', []);
    $violations = [];

    foreach (['app', 'database', 'routes', 'tests'] as $directory) {
        foreach ($layout->phpFiles($directory) as $path) {
            if (array_key_exists(Str::after($path, $layout->path().'/'), $exceptions)) {
                continue;
            }

            $source = (string) file_get_contents($path);

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $source, $matches)) {
                    foreach ($matches[0] as $match) {
                        $violations[] = Str::after($path, $layout->path().'/').': '.Str::limit($match, 80).' writes is_protected. Only '.class_basename(BootstrapAccessControlAction::class).' may, and only when it creates a record; in tests, run the access-control seeder and use the protected records it makes';
                    }
                }
            }
        }
    }

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});
