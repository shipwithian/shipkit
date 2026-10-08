<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

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

    it('rejects invalid, expired, and revoked bearer tokens', function () {
        $user = User::factory()->create();
        $expiredToken = $user->createToken('expired client', ['*'], now()->subMinute());
        $revokedToken = $user->createToken('revoked client', ['*']);
        $revokedToken->accessToken->delete();

        $this->withToken('invalid-token')
            ->getJson('/api/v1/user')
            ->assertUnauthorized();

        $this->withToken($expiredToken->plainTextToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();

        $this->withToken($revokedToken->plainTextToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();
    });
});

describe('logout', function () {
    it('revokes only the current bearer token', function () {
        $user = User::factory()->create();
        $firstToken = $user->createToken('first client', ['*']);
        $secondToken = $user->createToken('second client', ['*']);

        $this->withToken($firstToken->plainTextToken)
            ->postJson('/api/v1/logout')
            ->assertNoContent();

        expect(PersonalAccessToken::query()->find($firstToken->accessToken->id))->toBeNull();

        Auth::forgetGuards();

        $this->withToken($firstToken->plainTextToken)
            ->getJson('/api/v1/user')
            ->assertUnauthorized();

        $this->withToken($secondToken->plainTextToken)
            ->getJson('/api/v1/user')
            ->assertOk();
    });
});

describe('expiration', function () {
    it('defaults to ninety days and can be configured', function () {
        config(['sanctum.expiration' => 60]);

        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Short-lived client',
        ])->assertOk();

        $token = PersonalAccessToken::query()->where('name', 'Short-lived client')->firstOrFail();

        expect($token->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)))->toBeTrue();
    });
});
