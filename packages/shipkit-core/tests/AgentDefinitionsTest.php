<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

/**
 * The effort each model may run at, from the subagents guideline. `max` is never allowed.
 */
const EFFORT_BY_MODEL = [
    'haiku' => ['medium', 'high', 'xhigh'],
    'sonnet' => ['low', 'medium', 'high'],
    'opus' => ['low', 'medium'],
    'fable' => ['low', 'medium', 'high', 'xhigh'],
];

/**
 * @return array<string, string>
 */
function shippedAgentFrontmatter(string $path): array
{
    preg_match('/\A---\n(.*?)\n---\n/s', (string) file_get_contents($path), $match);

    $fields = [];

    foreach (explode("\n", $match[1] ?? '') as $line) {
        [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
        $fields[trim($key)] = trim($value);
    }

    return $fields;
}

it('pins a model and an effort in its range on every shipped agent', function () {
    $agents = File::files(dirname(__DIR__).'/resources/claude/agents');

    expect($agents)->not->toBeEmpty();

    foreach ($agents as $agent) {
        $fields = shippedAgentFrontmatter($agent->getPathname());

        expect($fields['name'] ?? null)->toBe($agent->getFilenameWithoutExtension())
            ->and($fields['description'] ?? '')->not->toBe('')
            ->and(array_key_exists($fields['model'] ?? '', EFFORT_BY_MODEL))->toBeTrue("{$agent->getFilename()}: model must be one of haiku, sonnet, opus, fable")
            ->and(in_array($fields['effort'] ?? '', EFFORT_BY_MODEL[$fields['model']] ?? [], true))->toBeTrue("{$agent->getFilename()}: effort is outside the {$fields['model']} range");
    }
});

it('only names agents that ShipKit ships in its skills and guidelines', function () {
    $resources = dirname(__DIR__).'/resources';
    $shipped = array_map(fn ($file) => $file->getFilenameWithoutExtension(), File::files("{$resources}/claude/agents"));
    $documents = [...glob("{$resources}/boost/skills/*/SKILL.md"), ...glob("{$resources}/boost/guidelines/*.md")];

    foreach ($documents as $document) {
        preg_match_all('/`(shipkit-[a-z]+(?:-[a-z]+)*)`/', (string) file_get_contents($document), $names);

        foreach (array_diff($names[1], ['shipkit-core'], $shipped) as $missing) {
            throw new RuntimeException(basename(dirname($document)).'/'.basename($document)." names the agent `{$missing}`, which ShipKit does not ship");
        }
    }

    expect($shipped)->not->toBeEmpty();
});
