<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Notifications\ApiVerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\PersonalAccessTokenResult;

class RegisterApiUserAction
{
    public function __construct(
        private readonly CreateNewUser $createNewUser,
        private readonly CreateApiTokenAction $createApiToken,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, password_confirmation: string}  $input
     * @return PersonalAccessTokenResult<mixed>
     */
    public function handle(array $input, string $deviceName): PersonalAccessTokenResult
    {
        $user = null;

        $token = DB::transaction(function () use ($input, $deviceName, &$user): PersonalAccessTokenResult {
            $user = $this->createNewUser->create($input);

            return $this->createApiToken->handle($user, $deviceName);
        });

        /** @var User $user */
        $user->notify(new ApiVerifyEmailNotification);

        return $token;
    }
}
