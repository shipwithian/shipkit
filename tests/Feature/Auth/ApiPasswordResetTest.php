<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('sends a reset link, resets the password, and revokes every token and connected app', function () {
    Notification::fake();

    $user = User::factory()->create();
    $oldToken = $user->createToken('old client', ['api']);
    $appToken = connectedAppToken($user);

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

    expect($oldToken->token->refresh()->revoked)->toBeTrue()
        ->and($appToken->refresh()->revoked)->toBeTrue()
        ->and($appToken->refreshToken->revoked)->toBeTrue();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'new-password',
        'device_name' => 'New client',
    ])->assertOk();
});
