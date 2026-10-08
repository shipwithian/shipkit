<?php

namespace App\Queries\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class PasskeyListQuery
{
    /**
     * @return Collection<int, Model>
     */
    public function handle(User $user): Collection
    {
        return $user->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get();
    }
}
