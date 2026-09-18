<?php

use App\Http\Controllers\Api\V1\AuthenticationController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

Route::get('up', function (): JsonResponse {
    $exception = null;

    try {
        Event::dispatch(new DiagnosingHealth);
    } catch (Throwable $e) {
        if (app()->hasDebugModeEnabled()) {
            throw $e;
        }

        report($e);
        $exception = $e;
    }

    return response()->json([
        'status' => $exception ? 'down' : 'up',
    ], $exception ? 500 : 200);
})->name('api.health');

Route::prefix('v1')
    ->group(function (): void {
        Route::post('register', [AuthenticationController::class, 'register'])
            ->middleware('throttle:api-register')
            ->name('api.v1.auth.register');

        Route::post('login', [AuthenticationController::class, 'login'])
            ->middleware('throttle:login')
            ->name('api.v1.auth.login');

        Route::post('two-factor-challenge', [AuthenticationController::class, 'twoFactorChallenge'])
            ->middleware('throttle:api-two-factor')
            ->name('api.v1.auth.two-factor-challenge');

        Route::post('forgot-password', [PasswordResetController::class, 'send'])
            ->middleware('throttle:api-password-reset')
            ->name('api.v1.password.email');

        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:api-password-reset')
            ->name('api.v1.password.update');

        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('api.v1.email.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthenticationController::class, 'logout'])
                ->name('api.v1.auth.logout');

            Route::get('user', CurrentUserController::class)
                ->name('api.v1.user');

            Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:api-verification')
                ->name('api.v1.email.verification-notification');
        });
    });
