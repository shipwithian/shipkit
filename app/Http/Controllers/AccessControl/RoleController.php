<?php

namespace App\Http\Controllers\AccessControl;

use App\Domain\AccessControl\Actions\CreateRoleAction;
use App\Domain\AccessControl\Actions\DeleteRoleAction;
use App\Domain\AccessControl\Actions\UpdateRoleAction;
use App\Domain\AccessControl\Models\Role;
use App\Domain\AccessControl\Queries\RoleListQuery;
use App\Http\Controllers\Controller;
use App\Http\Props\AccessControl\RoleAbilities;
use App\Http\Props\AccessControl\RoleIndexAbilities;
use App\Http\Props\AccessControl\RoleIndexProps;
use App\Http\Props\AccessControl\RoleListItem;
use App\Http\Requests\AccessControl\StoreRoleRequest;
use App\Http\Requests\AccessControl\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(RoleListQuery $roleList): Response
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('roles/index', new RoleIndexProps(
            roles: array_values(array_map(
                fn (Role $role): RoleListItem => RoleListItem::from($role, new RoleAbilities(
                    update: Gate::allows('update', $role),
                    delete: Gate::allows('delete', $role),
                    assignPermissions: Gate::allows('assignPermissions', $role),
                )),
                $roleList->handle()->all(),
            )),
            can: new RoleIndexAbilities(create: Gate::allows('create', Role::class)),
        ));
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
