<?php

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use ShipKit\Core\Channel;

class UpdateProfileAction
{
    /**
     * Update the user's own profile and log where the change came from.
     *
     * @param  array{name: string, email: string}  $attributes
     */
    public function handle(User $user, array $attributes, Channel $channel): User
    {
        $user->fill($attributes);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Log::info("Profile updated via {$channel->label()}.", [
            'user_id' => $user->id,
            'channel' => $channel->toContext(),
            'changed' => array_keys(Arr::except($user->getChanges(), ['updated_at'])),
        ]);

        return $user;
    }
}
