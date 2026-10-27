<?php

namespace App\Http\Controllers\AccessControl;

use App\Domain\AccessControl\Actions\SyncUserRolesAction;
use App\Domain\AccessControl\Models\Role;
use App\Domain\AccessControl\Queries\RoleListQuery;
use App\Domain\AccessControl\Queries\UserRoleListQuery;
use App\Http\Controllers\Controller;
use App\Http\Props\AccessControl\AssignableRole;
use App\Http\Props\AccessControl\UserRoleIndexProps;
use App\Http\Props\AccessControl\UserRoleListItem;
use App\Http\Props\Pagination;
use App\Http\Requests\AccessControl\UpdateUserRolesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserRoleController extends Controller
{
    public function index(UserRoleListQuery $userRoleList, RoleListQuery $roleList): Response
    {
        Gate::authorize('assignToUsers', Role::class);

        $users = $userRoleList->handle();

        return Inertia::render('users/index', new UserRoleIndexProps(
            users: array_values(array_map(UserRoleListItem::from(...), $users->items())),
            pagination: Pagination::from($users),
            roles: array_values(array_map(AssignableRole::from(...), $roleList->handle()->all())),
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
