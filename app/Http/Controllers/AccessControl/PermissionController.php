<?php

namespace App\Http\Controllers\AccessControl;

use App\Domain\AccessControl\Actions\CreatePermissionAction;
use App\Domain\AccessControl\Actions\DeletePermissionAction;
use App\Domain\AccessControl\Actions\UpdatePermissionAction;
use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Queries\PermissionListQuery;
use App\Http\Controllers\Controller;
use App\Http\Props\AccessControl\PermissionAbilities;
use App\Http\Props\AccessControl\PermissionIndexAbilities;
use App\Http\Props\AccessControl\PermissionIndexProps;
use App\Http\Props\AccessControl\PermissionListItem;
use App\Http\Requests\AccessControl\StorePermissionRequest;
use App\Http\Requests\AccessControl\UpdatePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(PermissionListQuery $permissionList): Response
    {
        Gate::authorize('viewAny', Permission::class);

        return Inertia::render('permissions/index', new PermissionIndexProps(
            permissions: array_values(array_map(
                fn (Permission $permission): PermissionListItem => PermissionListItem::from($permission, new PermissionAbilities(
                    update: Gate::allows('update', $permission),
                    delete: Gate::allows('delete', $permission),
                )),
                $permissionList->handle()->all(),
            )),
            can: new PermissionIndexAbilities(create: Gate::allows('create', Permission::class)),
        ));
    }

    public function store(
        StorePermissionRequest $request,
        CreatePermissionAction $createPermission,
    ): RedirectResponse {
        Gate::authorize('create', Permission::class);

        $createPermission->handle($request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission created.')]);

        return to_route('permissions.index');
    }

    public function update(
        UpdatePermissionRequest $request,
        Permission $permission,
        UpdatePermissionAction $updatePermission,
    ): RedirectResponse {
        Gate::authorize('update', $permission);

        $updatePermission->handle($permission, $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission updated.')]);

        return to_route('permissions.index');
    }

    public function destroy(
        Permission $permission,
        DeletePermissionAction $deletePermission,
    ): RedirectResponse {
        Gate::authorize('delete', $permission);

        $deletePermission->handle($permission);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission deleted.')]);

        return to_route('permissions.index');
    }
}
