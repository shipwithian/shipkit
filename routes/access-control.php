<?php

use App\Http\Controllers\AccessControl\PermissionController;
use App\Http\Controllers\AccessControl\RoleController;
use App\Http\Controllers\AccessControl\RolePermissionController;
use App\Http\Controllers\AccessControl\UserRoleController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

/*
| Access control lives in Settings, beside the account pages, so the main sidebar is free for the
| app's own work. Each page is guarded by its policy, and its link shows only to who may open it.
*/

Route::middleware(['auth', 'verified'])->prefix('settings')->group(function () {
    Route::resource('roles', RoleController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('permissions', PermissionController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('roles/{role}/permissions', [RolePermissionController::class, 'edit'])
        ->name('roles.permissions.edit');

    Route::put('roles/{role}/permissions', [RolePermissionController::class, 'update'])
        ->name('roles.permissions.update');

    Route::get('users', [UserRoleController::class, 'index'])
        ->name('users.index');

    Route::put('users/{user}/roles', [UserRoleController::class, 'update'])
        ->name('users.roles.update');
});

/*
| The addresses before access control moved into Settings, kept so links and bookmarks still arrive. They answer GET and HEAD
| only: Route::permanentRedirect() registers every verb, QUERY included since Laravel 13.40, and
| Wayfinder's generated types do not know QUERY yet.
*/

foreach ([
    'roles' => '/settings/roles',
    'roles/{role}/permissions' => '/settings/roles/{role}/permissions',
    'permissions' => '/settings/permissions',
    'users' => '/settings/users',
] as $old => $new) {
    Route::get($old, RedirectController::class)->defaults('destination', $new)->defaults('status', 301);
}
