<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function delete(User $user, User $model): bool
    {
        return $user->is($model) && ! $model->is_protected;
    }
}
