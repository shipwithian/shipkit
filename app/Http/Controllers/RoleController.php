<?php

namespace App\Http\Controllers;

use App\Actions\AccessControl\CreateRoleAction;
use App\Actions\AccessControl\DeleteRoleAction;
use App\Actions\AccessControl\UpdateRoleAction;
use App\Http\Requests\AccessControl\StoreRoleRequest;
use App\Http\Requests\AccessControl\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('roles/index', [
            'roles' => array_map(
                fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_protected' => $role->is_protected,
                    'permissions_count' => $role->permissions_count,
                    'users_count' => $role->users_count,
                ],
                Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get()->all(),
            ),
        ]);
    }

    public function store(StoreRoleRequest $request, CreateRoleAction $createRole): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $createRole->handle($request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role,
        UpdateRoleAction $updateRole,
    ): RedirectResponse {
        Gate::authorize('update', $role);

        $updateRole->handle($role, $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    public function destroy(Role $role, DeleteRoleAction $deleteRole): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $deleteRole->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }
}
