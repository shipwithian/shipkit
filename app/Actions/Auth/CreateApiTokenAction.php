<?php

namespace App\Actions\Auth;

use App\Enums\ApiScope;
use App\Models\User;
use Laravel\Passport\PersonalAccessTokenResult;

class CreateApiTokenAction
{
    /**
     * Issue a personal access token with the `api` and `mcp:use` scopes. It expires after
     * `passport.personal_access_token_lifetime_days`.
     *
     * @return PersonalAccessTokenResult<mixed>
     */
    public function handle(User $user, string $name): PersonalAccessTokenResult
    {
        return $user->createToken($name, [ApiScope::Api->value, ApiScope::McpUse->value]);
    }
}
