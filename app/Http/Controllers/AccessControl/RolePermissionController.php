<?php

namespace App\Http\Controllers\AccessControl;

use App\Domain\AccessControl\Actions\SyncRolePermissionsAction;
use App\Domain\AccessControl\Models\Role;
use App\Domain\AccessControl\Queries\AssignablePermissionListQuery;
use App\Http\Controllers\Controller;
use App\Http\Props\AccessControl\AssignablePermission;
use App\Http\Props\AccessControl\RolePermissionsEditProps;
use App\Http\Props\AccessControl\RoleWithPermissionIds;
use App\Http\Requests\AccessControl\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function edit(Role $role, AssignablePermissionListQuery $assignablePermissionList): Response
    {
        Gate::authorize('assignPermissions', $role);

        $role->loadMissing('permissions');

        return Inertia::render('roles/permissions', new RolePermissionsEditProps(
            role: RoleWithPermissionIds::from($role),
            permissions: array_values(array_map(AssignablePermission::from(...), $assignablePermissionList->handle()->all())),
        ));
    }

    public function update(
        UpdateRolePermissionsRequest $request,
        Role $role,
        SyncRolePermissionsAction $syncRolePermissions,
    ): RedirectResponse {
        Gate::authorize('assignPermissions', $role);

        /** @var array<int, int> $permissionIds */
        $permissionIds = $request->validated('permissions');
        $syncRolePermissions->handle($role, $permissionIds);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role permissions updated.')]);

        return to_route('roles.permissions.edit', $role);
    }
}
