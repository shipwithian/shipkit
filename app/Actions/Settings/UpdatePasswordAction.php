<?php

namespace App\Actions\Settings;

use App\Models\User;

class UpdatePasswordAction
{
    public function handle(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }
}
