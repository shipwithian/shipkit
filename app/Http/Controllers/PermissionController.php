<?php

namespace App\Http\Controllers;

use App\Actions\AccessControl\CreatePermissionAction;
use App\Actions\AccessControl\DeletePermissionAction;
use App\Actions\AccessControl\UpdatePermissionAction;
use App\Http\Requests\AccessControl\StorePermissionRequest;
use App\Http\Requests\AccessControl\UpdatePermissionRequest;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Permission::class);

        return Inertia::render('permissions/index', [
            'permissions' => array_map(
                fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'is_protected' => $permission->is_protected,
                    'roles_count' => $permission->roles_count,
                    'users_count' => $permission->users_count,
                ],
                Permission::query()->withCount(['roles', 'users'])->orderBy('name')->get()->all(),
            ),
        ]);
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
