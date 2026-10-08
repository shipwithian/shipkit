<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\ResetApiPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
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
        ResetApiPasswordAction $resetApiPassword,
    ): JsonResponse {
        /** @var array{token: string, email: string, password: string, password_confirmation: string} $validated */
        $validated = $request->validated();

        $resetApiPassword->handle($validated);

        return response()->json([
            'message' => __('Your password has been reset.'),
        ]);
    }
}
