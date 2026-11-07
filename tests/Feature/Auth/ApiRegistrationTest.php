<?php

use App\Models\User;
use App\Notifications\ApiVerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

it('registers a user and issues a bearer token', function () {
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
