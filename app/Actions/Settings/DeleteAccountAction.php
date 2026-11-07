<?php

namespace App\Actions\Settings;

use App\Exceptions\ProtectedAccountCannotBeDeleted;
use App\Models\User;

class DeleteAccountAction
{
    /**
     * @throws ProtectedAccountCannotBeDeleted
     */
    public function handle(User $user): void
    {
        $user->delete();
    }
}
