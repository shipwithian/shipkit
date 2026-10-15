<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->resolveDomainFactories();
    }

    /**
     * Throttle authenticated API requests that change state, per user.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api-write', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Map factories to models by mirroring their namespace, so a factory in
     * database/factories/Domain/{Context}/Models resolves to app/Domain/{Context}/Models.
     */
    protected function resolveDomainFactories(): void
    {
        Factory::guessModelNamesUsing(function (Factory $factory): string {
            $model = Str::replaceLast('Factory', '', Str::after($factory::class, 'Database\\Factories\\'));

            foreach (["App\\{$model}", "App\\Models\\{$model}"] as $candidate) {
                if (is_subclass_of($candidate, Model::class)) {
                    return $candidate;
                }
            }

            throw new LogicException('No model found for '.$factory::class.'; place it at app/'.str_replace('\\', '/', $model).'.php or set $model.');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
