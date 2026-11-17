<?php

namespace App\Providers;

use App\Http\Middleware\ShareChannel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use ShipKit\Core\Channel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Channel::class, ShareChannel::current(...));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configurePublicAbilities();
    }

    /**
     * Abilities anyone may use, signed in or not, for the public MCP server's tools. They take a
     * nullable user, because a guest's tool call authorizes with no one signed in.
     */
    protected function configurePublicAbilities(): void
    {
        Gate::define('viewAppInfo', fn (?User $user): bool => true);
    }

    /**
     * Throttle authenticated API requests that change state and MCP calls, per user, and AI client
     * self-registration and public MCP calls, per address.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api-write', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('mcp-public', fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->ip()));

        RateLimiter::for('mcp-register', fn (Request $request): Limit => $request->isMethod('POST')
            ? Limit::perMinute(10)->by($request->ip())
            : Limit::none());
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
