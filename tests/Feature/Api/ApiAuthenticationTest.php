<?php

use App\Models\User;
use App\Notifications\ApiVerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

test('guest API requests return JSON unauthorized responses', function () {
    $this->getJson('/api/v1/user')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('API routes do not authenticate browser sessions', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->getJson('/api/v1/user')
        ->assertUnauthorized();
});

test('users can register through the API and receive a bearer token', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/register', [
        'name' => 'API User',
        'email' => 'api@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'Mobile app',
    ]);

    $response->assertCreated();

    $user = User::query()->where('email', 'api@example.com')->firstOrFail();

    $response
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.permissions', []);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    Notification::assertSentTo($user, ApiVerifyEmailNotification::class);
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(1);
});

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

test('the current API user includes current permissions without exposing secrets', function () {
    $user = userWithPermissions(['reports.view']);
    $token = $user->createToken('test client', ['*'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.permissions', ['reports.view'])
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.two_factor_secret')
        ->assertJsonMissingPath('data.two_factor_recovery_codes')
        ->assertJsonMissingPath('data.token');
});

test('API logout revokes only the current bearer token', function () {
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

test('invalid expired and revoked bearer tokens are rejected', function () {
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

test('API email verification uses a signed link', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute(
        'api.v1.email.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('message', 'Your email address has been verified.');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('API users can request a verification email', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $token = $user->createToken('mobile app', ['*'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/email/verification-notification')
        ->assertAccepted();

    Notification::assertSentTo($user, ApiVerifyEmailNotification::class);
});

test('API users can request and complete a password reset', function () {
    Notification::fake();

    $user = User::factory()->create();
    $oldToken = $user->createToken('old client', ['*']);

    $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
        ->assertAccepted();

    $notification = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $sent) use (&$notification): bool {
        $notification = $sent;

        return true;
    });

    $this->postJson('/api/v1/reset-password', [
        'token' => $notification->token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Your password has been reset.');

    expect(PersonalAccessToken::query()->find($oldToken->accessToken->id))->toBeNull();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'new-password',
        'device_name' => 'New client',
    ])->assertOk();
});

test('API token expiration defaults to ninety days and can be configured', function () {
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
