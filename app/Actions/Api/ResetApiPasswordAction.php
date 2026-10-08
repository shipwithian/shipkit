<?php

namespace App\Actions\Api;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class ResetApiPasswordAction
{
    public function __construct(
        private readonly ResetUserPassword $resetUserPassword,
    ) {}

    /**
     * @param  array{token: string, email: string, password: string, password_confirmation: string}  $credentials
     */
    public function handle(array $credentials): void
    {
        $status = Password::broker(config('fortify.passwords'))->reset(
            $credentials,
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $this->resetUserPassword->reset($user, [
                        'password' => $password,
                        'password_confirmation' => $password,
                    ]);

                    $user->setRememberToken(Str::random(60));
                    $user->save();
                    $user->tokens()->delete();
                });

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                Fortify::email() => [__('The password reset token is invalid or expired.')],
            ]);
        }
    }
}
