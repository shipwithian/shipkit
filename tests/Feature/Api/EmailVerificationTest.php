<?php

use App\Models\User;
use App\Notifications\ApiVerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

describe('send', function () {
    it('sends a verification email', function () {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = $user->createToken('mobile app', ['*'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/email/verification-notification')
            ->assertAccepted();

        Notification::assertSentTo($user, ApiVerifyEmailNotification::class);
    });
});

describe('verify', function () {
    it('verifies the email through a signed link', function () {
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
});
