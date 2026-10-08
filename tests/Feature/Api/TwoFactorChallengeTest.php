<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

test('users can complete an API two factor challenge with a TOTP code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $login = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Mobile app',
    ]);

    $response = $this->postJson('/api/v1/two-factor-challenge', [
        'challenge_token' => $login->json('challenge_token'),
        'code' => app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id);

    expect(PersonalAccessToken::query()->count())->toBe(1);
});

test('users can complete an API two factor challenge with a recovery code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $login = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Mobile app',
    ]);

    $this->postJson('/api/v1/two-factor-challenge', [
        'challenge_token' => $login->json('challenge_token'),
        'recovery_code' => 'recovery-code-1',
    ])->assertOk();

    expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

test('invalid API two factor codes do not issue a token', function () {
    $user = User::factory()->withTwoFactor()->create();

    $login = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Mobile app',
    ]);

    $this->postJson('/api/v1/two-factor-challenge', [
        'challenge_token' => $login->json('challenge_token'),
        'code' => '000000',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

test('an API two factor challenge can only be used once', function () {
    $user = User::factory()->withTwoFactor()->create();

    $login = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Mobile app',
    ]);

    $challenge = $login->json('challenge_token');
    $code = app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP');

    $this->postJson('/api/v1/two-factor-challenge', [
        'challenge_token' => $challenge,
        'code' => $code,
    ])->assertOk();

    $this->postJson('/api/v1/two-factor-challenge', [
        'challenge_token' => $challenge,
        'code' => $code,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_token');
});
