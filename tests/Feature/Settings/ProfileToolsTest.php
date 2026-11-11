<?php

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\Settings\ShowMyProfileTool;
use App\Mcp\Tools\Settings\UpdateMyProfileTool;
use App\Models\User;

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

    it('marks a changed email unverified, as the web form does', function () {
        $user = User::factory()->create();

        AppServer::actingAs($user)->tool(UpdateMyProfileTool::class, ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertOk()
            ->assertStructuredContentPath('email', 'new@example.com')
            ->assertStructuredContentPath('email_verified', false);

        expect($user->refresh()->email_verified_at)->toBeNull();
    });
});
