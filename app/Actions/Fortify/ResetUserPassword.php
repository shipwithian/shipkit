<?php

namespace App\Actions\Fortify;

use App\Actions\Auth\RevokeAllApiAccessAction;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly RevokeAllApiAccessAction $revokeAllApiAccess) {}

    /**
     * Validate and reset the user's forgotten password, and revoke every personal access token and
     * connected app, so nothing stays connected after a suspected compromise.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();

        $this->revokeAllApiAccess->handle($user);
    }
}
