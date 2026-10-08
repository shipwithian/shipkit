<?php

namespace App\Actions\Api;

use App\Actions\ApiTokens\CreateApiTokenAction;
use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Notifications\ApiVerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

class RegisterApiUserAction
{
    public function __construct(
        private readonly CreateNewUser $createNewUser,
        private readonly CreateApiTokenAction $createApiToken,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, password_confirmation: string}  $input
     */
    public function handle(array $input, string $deviceName): NewAccessToken
    {
        $token = DB::transaction(fn (): NewAccessToken => $this->createApiToken->handle(
            $this->createNewUser->create($input),
            $deviceName,
        ));

        /** @var User $user */
        $user = $token->accessToken->tokenable;
        $user->notify(new ApiVerifyEmailNotification);

        return $token;
    }
}
