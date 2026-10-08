<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('users can log in through the API without creating a web session', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Mobile app',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id);

    $this->assertGuest();
    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

test('API login rejects invalid credentials with a validation error', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Mobile app',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('API login requires a two factor challenge before issuing a token', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Mobile app',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('two_factor_required', true)
        ->assertJsonStructure(['challenge_token', 'expires_at']);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});
