<?php

namespace App\Actions\Settings;

use App\Models\User;

class UpdateProfileAction
{
    /**
     * @param  array{name: string, email: string}  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        $user->fill($attributes);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }
}
