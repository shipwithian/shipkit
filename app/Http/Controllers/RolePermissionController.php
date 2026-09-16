<?php

namespace App\Http\Controllers;

use App\Actions\AccessControl\SyncRolePermissionsAction;
use App\Http\Requests\AccessControl\UpdateRolePermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function edit(Role $role): Response
    {
        Gate::authorize('assignPermissions', $role);

        return Inertia::render('roles/permissions', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_protected' => $role->is_protected,
                'permission_ids' => $role->permissions()->pluck('permissions.id'),
            ],
            'permissions' => array_map(
                fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'is_protected' => $permission->is_protected,
                ],
                Permission::query()->orderBy('name')->get()->all(),
            ),
        ]);
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
