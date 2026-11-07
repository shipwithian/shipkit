<?php

namespace ShipKit\Core;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;
use ShipKit\Core\Console\MakeFeatureCommand;
use ShipKit\Core\Console\VerifyCommand;
use Symfony\Component\Process\ExecutableFinder;

class ShipKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shipkit.php', 'shipkit');

        $this->app->singleton(GuardedModelMap::class);
    }

    /**
     * Refuse to boot with a mapped model that could bypass its invariants.
     */
    public function boot(): void
    {
        $this->app->make(GuardedModelMap::class)->ensureMappedModelsAreGuarded();

        if ($this->app->runningInConsole()) {
            $this->commands([MakeFeatureCommand::class, VerifyCommand::class]);

            $this->publishes([
                __DIR__.'/../config/shipkit.php' => config_path('shipkit.php'),
            ], 'shipkit-config');

            $this->registerMailpitDevCommand();
        }
    }

    /**
     * Run Mailpit as the `mail` process of `composer dev` in a local environment where it is
     * installed, so mail sent to 127.0.0.1:1025 can be read at http://localhost:8025.
     */
    protected function registerMailpitDevCommand(): void
    {
        if (! class_exists(DevCommands::class)
            || ! $this->app->isLocal()
            || ! config('shipkit.dev.mailpit')
            || $this->app->make(ExecutableFinder::class)->find('mailpit') === null) {
            return;
        }

        DevCommands::register('mailpit', 'mail')->orange();
    }
}
