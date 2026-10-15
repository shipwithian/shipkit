<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Exceptions\ProtectedAccountCannotBeDeleted;
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
