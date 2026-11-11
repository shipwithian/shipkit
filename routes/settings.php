<?php

use App\Http\Controllers\Settings\ConnectedAppController;
use App\Http\Controllers\Settings\PasskeyEndpointsController;
use App\Http\Controllers\Settings\PersonalAccessTokenController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::post('settings/security/tokens', [PersonalAccessTokenController::class, 'store'])
        ->middleware(['throttle:6,1', RequirePassword::class])
        ->name('personal-access-tokens.store');

    Route::delete('settings/security/tokens/{token}', [PersonalAccessTokenController::class, 'destroy'])
        ->where('token', '[A-Za-z0-9]+')
        ->middleware('throttle:api-write')
        ->name('personal-access-tokens.destroy');

    Route::delete('settings/security/connected-apps/{client}', [ConnectedAppController::class, 'destroy'])
        ->whereUuid('client')
        ->middleware('throttle:api-write')
        ->name('connected-apps.destroy');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', PasskeyEndpointsController::class)
    ->name('well-known.passkeys');
