<?php

namespace App\Actions\Api;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AuthenticateApiUserAction
{
    public function handle(LoginRequest $request): User
    {
        $guard = Auth::guard((string) config('fortify.guard', 'web'));
        $provider = $guard->getProvider();

        if (Fortify::$authenticateUsingCallback) {
            $user = call_user_func(Fortify::$authenticateUsingCallback, $request);
        } else {
            $user = $provider->retrieveByCredentials(
                $request->only(Fortify::username(), 'password'),
            );

            if ($user && $provider->validateCredentials($user, [
                'password' => $request->string('password')->toString(),
            ])) {
                if (config('hashing.rehash_on_login', true)) {
                    $provider->rehashPasswordIfRequired($user, [
                        'password' => $request->string('password')->toString(),
                    ]);
                }
            } else {
                $user = null;
            }
        }

        if (! $user instanceof User) {
            event(new Failed($guard->getName(), null, [
                Fortify::username() => $request->input(Fortify::username()),
                'password' => $request->input('password'),
            ]));

            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.failed')],
            ]);
        }

        return $user;
    }
}
