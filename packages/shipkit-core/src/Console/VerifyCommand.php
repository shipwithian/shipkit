<?php

namespace ShipKit\Core\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ShipKit\Core\GuardedModelMap;
use SimpleXMLElement;

#[Signature('shipkit:verify')]
#[Description("Fail unless ShipKit's checks run in this project and its guarded models are mapped")]
class VerifyCommand extends Command
{
    /**
     * The directory, relative to the project, that holds ShipKit's checks.
     */
    public const string TESTS_DIRECTORY = 'vendor/shipwithian/shipkit-core/tests';

    public function handle(GuardedModelMap $models): int
    {
        $problems = [
            ...$this->testsuiteProblems(),
            ...$this->configProblems($models),
        ];

        if ($problems !== []) {
            $this->components->error('ShipKit is not fully enforced in this project.');
            $this->components->bulletList($problems);

            return self::FAILURE;
        }

        $this->components->info("ShipKit's checks are registered and its guarded models are mapped.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function testsuiteProblems(): array
    {
        $missing = 'Register ShipKit\'s checks in phpunit.xml: <testsuite name="ShipKit"><directory>'.self::TESTS_DIRECTORY.'</directory></testsuite>. Without it, none of them run.';
        $path = collect([base_path('phpunit.xml'), base_path('phpunit.xml.dist')])->first(fn (string $path): bool => is_file($path));

        if ($path === null) {
            return [$missing];
        }

        $xml = simplexml_load_string((string) file_get_contents($path));
        $directories = $xml instanceof SimpleXMLElement ? $xml->xpath('//testsuites/testsuite/directory') : [];

        foreach ($directories ?: [] as $directory) {
            if (Str::of((string) $directory)->trim()->chopStart('./')->rtrim('/')->toString() === self::TESTS_DIRECTORY) {
                return [];
            }
        }

        return [$missing];
    }

    /**
     * @return list<string>
     */
    private function configProblems(GuardedModelMap $models): array
    {
        if (! is_file(config_path('shipkit.php'))) {
            return ['Publish config/shipkit.php and map the application\'s guarded models: php artisan vendor:publish --tag=shipkit-config'];
        }

        return $models->misconfigurations();
    }
}
