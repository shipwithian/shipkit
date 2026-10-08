<?php

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteAccountAction
{
    public function handle(User $user): void
    {
        if ($user->is_protected) {
            throw ValidationException::withMessages([
                'user' => __('Protected accounts cannot be deleted.'),
            ]);
        }

        $user->delete();
    }
}
