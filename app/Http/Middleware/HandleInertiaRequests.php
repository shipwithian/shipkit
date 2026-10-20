<?php

namespace App\Http\Middleware;

use App\Http\Props\AuthProps;
use App\Http\Props\SharedProps;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * The typed `SharedProps` sits in the list next to Inertia's own shared props, and Inertia
     * expands it into its public properties.
     *
     * @return array<array-key, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            new SharedProps(
                name: config()->string('app.name'),
                auth: AuthProps::for($request->user()),
                sidebarOpen: ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ),
        ];
    }
}
