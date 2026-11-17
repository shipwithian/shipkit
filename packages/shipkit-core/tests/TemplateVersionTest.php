<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\File;
use ShipKit\Core\Upgrade\TemplateVersion;

uses(TestCase::class);

beforeEach(function () {
    $this->project = sys_get_temp_dir().'/shipkit-template-version-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->project);
    app()->setBasePath($this->project);
});

afterEach(function () {
    File::deleteDirectory($this->project);
});

it('reads the template version, with ShipKit as the source and nothing excluded by default', function () {
    File::put("{$this->project}/shipkit.json", '{"template": "5.3.0"}');

    $template = new TemplateVersion;

    expect($template->version())->toBe('5.3.0')
        ->and($template->source())->toBe(TemplateVersion::DEFAULT_SOURCE)
        ->and($template->excluded())->toBe([]);
});

it('reads a project\'s own source and excluded paths', function () {
    File::put("{$this->project}/shipkit.json", '{"template": "5.3.0", "source": "https://git.example.com/mirror.git", "exclude": ["README.md", "resources/js/pages/welcome"]}');

    $template = new TemplateVersion;

    expect($template->source())->toBe('https://git.example.com/mirror.git')
        ->and($template->excluded())->toBe(['README.md', 'resources/js/pages/welcome']);
});

it('has no version in a project that never recorded one, or on ShipKit\'s own development branch', function (?string $contents) {
    if ($contents !== null) {
        File::put("{$this->project}/shipkit.json", $contents);
    }

    expect((new TemplateVersion)->version())->toBeNull();
})->with([
    'no file' => [null],
    'the development marker' => ['{"template": "dev"}'],
    'no template key' => ['{"exclude": []}'],
]);

it('refuses a file that is not valid JSON, naming it', function () {
    File::put("{$this->project}/shipkit.json", '{"template": ');

    expect(fn () => (new TemplateVersion)->version())->toThrow(InvalidArgumentException::class, 'shipkit.json');
});

it('refuses a version that is not major.minor.patch', function () {
    File::put("{$this->project}/shipkit.json", '{"template": "latest"}');

    expect(fn () => (new TemplateVersion)->version())->toThrow(InvalidArgumentException::class, 'major.minor.patch');
});

it('stamps a new version and keeps the project\'s other settings', function () {
    File::put("{$this->project}/shipkit.json", '{"template": "5.2.0", "exclude": ["README.md"]}');

    (new TemplateVersion)->stamp('5.3.0');

    expect(json_decode(File::get("{$this->project}/shipkit.json"), true))->toBe(['template' => '5.3.0', 'exclude' => ['README.md']])
        ->and(File::get("{$this->project}/shipkit.json"))->toEndWith("}\n");
});

it('creates the file when it stamps a project that had none', function () {
    (new TemplateVersion)->stamp('5.3.0');

    expect((new TemplateVersion)->version())->toBe('5.3.0');
});
