<?php

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\Settings\ShowMyProfileTool;
use App\Mcp\Tools\Settings\UpdateMyProfileTool;
use App\Models\User;
use Illuminate\Support\Facades\Log;

describe('show_my_profile', function () {
    it('returns exactly the listed profile fields', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

        AppServer::actingAs($user)->tool(ShowMyProfileTool::class)
            ->assertOk()
            ->assertExactStructuredContent([
                'id' => $user->id,
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'email_verified' => true,
            ]);
    });
});

describe('update_my_profile', function () {
    it('explains invalid input so the AI client can retry', function () {
        $user = User::factory()->create();
        $taken = User::factory()->create();

        AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, ['name' => '', 'email' => $taken->email])
            ->assertHasErrors([
                'Give the user\'s full name, for example "Ada Lovelace".',
                'Another account already uses this email address; ask the user for a different one.',
            ]);
    });

    it('updates the name and keeps a verified email verified', function () {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, ['name' => 'Ada King', 'email' => 'ada@example.com'])
            ->assertOk()
            ->assertStructuredContentPath('name', 'Ada King')
            ->assertStructuredContentPath('email_verified', true);

        expect($user->refresh()->name)->toBe('Ada King');
    });

    it('asks for confirmation before changing the email, and changes nothing yet', function () {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertOk()
            ->assertStructuredContentPath('confirmed', false)
            ->assertStructuredContentPath('summary', 'Change your email from ada@example.com to new@example.com. It will be unverified until you confirm the new address.')
            ->assertStructuredContentPath('expires_in_minutes', 10);

        expect($user->refresh()->email)->toBe('ada@example.com');
    });

    it('names a name change sent together with the email change in the summary', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

        AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, ['name' => 'Ada King', 'email' => 'new@example.com'])
            ->assertStructuredContentPath('summary', 'Change your email from ada@example.com to new@example.com. It will be unverified until you confirm the new address. Also change your name from Ada Lovelace to Ada King.');

        expect($user->refresh()->name)->toBe('Ada Lovelace');
    });
    it('marks a confirmed email change unverified, as the web form does', function () {
        $user = User::factory()->create();
        $arguments = ['name' => $user->name, 'email' => 'new@example.com'];
        $token = confirmationTokenFrom(AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, $arguments));

        AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, [...$arguments, 'confirmation_token' => $token])
            ->assertOk()
            ->assertStructuredContentPath('confirmed', true)
            ->assertStructuredContentPath('email', 'new@example.com')
            ->assertStructuredContentPath('email_verified', false);

        expect($user->refresh()->email_verified_at)->toBeNull();
    });

    it('returns the first result without acting again when a confirmed email change is repeated', function () {
        Log::spy();

        $user = User::factory()->create();
        $arguments = ['name' => $user->name, 'email' => 'new@example.com'];
        $token = confirmationTokenFrom(AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, $arguments));

        foreach ([1, 2] as $attempt) {
            AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, [...$arguments, 'confirmation_token' => $token])
                ->assertOk()
                ->assertStructuredContentPath('confirmed', true)
                ->assertStructuredContentPath('email', 'new@example.com');
        }

        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_starts_with($message, 'Profile updated via'))->once();
    });

    it('rejects a confirmation token for a different email', function () {
        $user = User::factory()->create();
        $token = confirmationTokenFrom(AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, ['name' => $user->name, 'email' => 'new@example.com']));

        AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, ['name' => $user->name, 'email' => 'other@example.com', 'confirmation_token' => $token])
            ->assertHasErrors();

        expect($user->refresh()->email)->not->toBe('other@example.com');
    });
});
