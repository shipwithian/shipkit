<?php

namespace App\Actions\Api;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AuthenticateApiUserAction
{
    public function handle(string $username, string $password): User
    {
        $guard = Auth::guard((string) config('fortify.guard', 'web'));
        $provider = $guard->getProvider();

        $user = $provider->retrieveByCredentials([
            Fortify::username() => $username,
            'password' => $password,
        ]);

        if (! $user instanceof User || ! $provider->validateCredentials($user, ['password' => $password])) {
            event(new Failed($guard->getName(), null, [
                Fortify::username() => $username,
                'password' => $password,
            ]));

            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.failed')],
            ]);
        }

        if (config('hashing.rehash_on_login', true)) {
            $provider->rehashPasswordIfRequired($user, ['password' => $password]);
        }

        return $user;
    }
}
