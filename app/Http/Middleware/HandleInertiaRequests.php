<?php

namespace App\Http\Middleware;

use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Http\Props\AuthProps;
use App\Http\Props\NavigationAbilities;
use App\Http\Props\SharedProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
     * expands it into its public properties. The navigation abilities come from the same policy
     * abilities the access control index controllers authorize.
     *
     * @return array<array-key, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            new SharedProps(
                name: config()->string('app.name'),
                auth: AuthProps::for($request->user(), new NavigationAbilities(
                    viewRoles: Gate::allows('viewAny', Role::class),
                    viewPermissions: Gate::allows('viewAny', Permission::class),
                    viewUsers: Gate::allows('assignToUsers', Role::class),
                )),
                sidebarOpen: ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ),
        ];
    }
}
