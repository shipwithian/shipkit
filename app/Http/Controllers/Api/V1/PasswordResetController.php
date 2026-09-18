<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class PasswordResetController extends Controller
{
    public function send(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker(config('fortify.passwords'))->sendResetLink(
            $request->only(Fortify::email()),
        );

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => __('Please wait before requesting another password reset link.'),
            ], 429);
        }

        return response()->json([
            'message' => __('If an account matches that email address, a password reset link has been sent.'),
        ], 202);
    }

    public function reset(
        ResetPasswordRequest $request,
        ResetUserPassword $resetUserPassword,
    ): JsonResponse {
        /** @var array{token: string, email: string, password: string, password_confirmation: string} $validated */
        $validated = $request->validated();

        $status = Password::broker(config('fortify.passwords'))->reset(
            $validated,
            function (User $user, string $password) use ($resetUserPassword): void {
                $resetUserPassword->reset($user, [
                    'password' => $password,
                    'password_confirmation' => $password,
                ]);

                $user->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                Fortify::email() => [__('The password reset token is invalid or expired.')],
            ]);
        }

        return response()->json([
            'message' => __('Your password has been reset.'),
        ]);
    }
}
