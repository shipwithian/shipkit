<?php

namespace App\Http\Controllers\AccessControl;

use App\Actions\AccessControl\SyncUserRolesAction;
use App\Http\Controllers\Controller;
use App\Http\Props\AccessControl\AssignableRole;
use App\Http\Props\AccessControl\UserListFilters;
use App\Http\Props\AccessControl\UserRoleIndexProps;
use App\Http\Props\AccessControl\UserRoleListItem;
use App\Http\Props\Pagination;
use App\Http\Requests\AccessControl\UpdateUserRolesRequest;
use App\Http\Requests\AccessControl\UserListRequest;
use App\Models\Role;
use App\Models\User;
use App\Queries\AccessControl\RoleListQuery;
use App\Queries\AccessControl\UserRoleListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserRoleController extends Controller
{
    public function index(UserListRequest $request, UserRoleListQuery $userRoleList, RoleListQuery $roleList): Response
    {
        Gate::authorize('assignToUsers', Role::class);

        $users = $userRoleList->handle($request->search(), $request->roleId());

        return Inertia::render('users/index', new UserRoleIndexProps(
            users: array_values(array_map(UserRoleListItem::from(...), $users->items())),
            pagination: Pagination::from($users),
            roles: array_values(array_map(AssignableRole::from(...), $roleList->handle()->all())),
            filters: new UserListFilters(search: $request->search(), role: $request->roleId()),
        ));
    }

    public function update(
        UpdateUserRolesRequest $request,
        User $user,
        SyncUserRolesAction $syncUserRoles,
    ): RedirectResponse {
        Gate::authorize('assignToUsers', Role::class);

        /** @var array<int, int> $roleIds */
        $roleIds = $request->validated('roles');
        /** @var User $actor */
        $actor = $request->user();
        $syncUserRoles->handle($actor, $user, $roleIds);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User roles updated.')]);

        return back();
    }
}
