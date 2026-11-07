<?php

namespace App\Queries\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Passkeys\Passkey;

class PasskeyListQuery
{
    /**
     * @return Collection<int, Passkey>
     */
    public function handle(User $user): Collection
    {
        return $user->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get();
    }
}
