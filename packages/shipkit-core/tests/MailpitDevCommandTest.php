<?php

use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Testing\TestCase;
use ShipKit\Core\ShipKitServiceProvider;
use Symfony\Component\Process\ExecutableFinder;

uses(TestCase::class);

/**
 * Boot ShipKit's provider again in the given environment, with Mailpit installed or not, and
 * return the names of the registered dev commands.
 *
 * @return list<string>
 */
function devCommandsWhenBooted(string $environment, bool $mailpitInstalled): array
{
    app()->instance(ExecutableFinder::class, new class($mailpitInstalled) extends ExecutableFinder
    {
        public function __construct(private bool $mailpitInstalled) {}

        public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
        {
            return $name === 'mailpit' && $this->mailpitInstalled ? '/usr/local/bin/mailpit' : $default;
        }
    });

    app()->detectEnvironment(fn (): string => $environment);

    (new ShipKitServiceProvider(app()))->boot();

    return array_column(DevCommands::commands(), 'name');
}

beforeEach(function () {
    $commands = new ReflectionProperty(DevCommands::class, 'commands');
    $this->devCommandsBefore = $commands->getValue();
    $commands->setValue(null, []);
});

afterEach(function () {
    (new ReflectionProperty(DevCommands::class, 'commands'))->setValue(null, $this->devCommandsBefore);
});

it('runs Mailpit with composer dev in a local environment where it is installed', function () {
    expect(devCommandsWhenBooted('local', mailpitInstalled: true))->toContain('mail');
});

it('leaves Mailpit out when it is not installed, outside a local environment, or turned off', function () {
    expect(devCommandsWhenBooted('local', mailpitInstalled: false))->not->toContain('mail')
        ->and(devCommandsWhenBooted('production', mailpitInstalled: true))->not->toContain('mail');

    config(['shipkit.dev.mailpit' => false]);

    expect(devCommandsWhenBooted('local', mailpitInstalled: true))->not->toContain('mail');
});
