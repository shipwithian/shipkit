<?php

use App\Models\User;
use App\Providers\PassportServiceProvider;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

describe('authentication', function () {
    it('rejects guest requests with a JSON unauthorized response', function () {
        $this->getJson('/api/v1/user')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    });

    it('does not authenticate browser sessions', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    });

    it('rejects invalid and revoked bearer tokens', function () {
        $user = User::factory()->create();
        $revokedToken = $user->createToken('revoked client', ['api']);
        $revokedToken->token->revoke();

        $this->withToken('invalid-token')
            ->getJson('/api/v1/user')
            ->assertUnauthorized();

        $this->withToken($revokedToken->accessToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    });

    it('rejects an expired personal access token', function () {
        Passport::personalAccessTokensExpireIn(CarbonInterval::second());
        $token = User::factory()->create()->createToken('old script', ['api']);

        sleep(2);

        $this->withToken($token->accessToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    });

    it('rejects a token without the api scope', function () {
        $token = User::factory()->create()->createToken('no scopes');

        $this->withToken($token->accessToken)
            ->getJson('/api/v1/user')
            ->assertForbidden();
    });
});

describe('logout', function () {
    it('revokes only the current bearer token', function () {
        $user = User::factory()->create();
        $firstToken = $user->createToken('first client', ['api']);
        $secondToken = $user->createToken('second client', ['api']);

        $this->withToken($firstToken->accessToken)
            ->postJson('/api/v1/logout')
            ->assertNoContent();

        expect($firstToken->token->refresh()->revoked)->toBeTrue();

        Auth::forgetGuards();

        $this->withToken($firstToken->accessToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();

        $this->withToken($secondToken->accessToken)
            ->getJson('/api/v1/user')
            ->assertOk();
    });
});

describe('expiration', function () {
    it('expires personal access tokens after ninety days by default', function () {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Laptop',
        ])->assertOk();

        $token = Token::query()->where('name', 'Laptop')->firstOrFail();

        expect($token->expires_at->between(now()->addDays(90)->subMinute(), now()->addDays(90)->addMinute()))->toBeTrue();
    });

    it('takes the lifetime from configuration', function () {
        config(['passport.personal_access_token_lifetime_days' => 7]);
        (new PassportServiceProvider(app()))->boot();

        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Short-lived client',
        ])->assertOk()
            ->assertJsonPath('expires_at', fn (string $expiresAt): bool => now()->addDays(7)->diffInMinutes($expiresAt, true) < 2);
    });
});
