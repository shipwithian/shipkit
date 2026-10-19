<?php

namespace ShipKit\Core;

use Illuminate\Support\ServiceProvider;
use ShipKit\Core\Console\MakeContextCommand;
use ShipKit\Core\Console\VerifyCommand;

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
            $this->commands([MakeContextCommand::class, VerifyCommand::class]);

            $this->publishes([
                __DIR__.'/../config/shipkit.php' => config_path('shipkit.php'),
            ], 'shipkit-config');
        }
    }
}
