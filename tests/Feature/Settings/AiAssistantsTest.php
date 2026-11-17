<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the addresses an AI assistant connects to, without asking for the password again', function () {
    config(['app.name' => 'Satin Cafe']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ai-assistants.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai-assistants')
            ->where('serverName', 'Satin Cafe')
            ->where('mcpUrl', url('/mcp'))
            ->where('publicMcpUrl', url('/mcp/public'))
            ->where('connectedApps', []),
        );
});

it('lists the apps the user connected', function () {
    $user = User::factory()->create();
    connectedAppToken($user, oauthApp('Claude'));

    $this->actingAs($user)
        ->get(route('ai-assistants.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('connectedApps', 1)
            ->where('connectedApps.0.name', 'Claude'),
        );
});

it('rejects guests', function () {
    $this->get(route('ai-assistants.edit'))->assertRedirect(route('login'));
});

it('asks an unverified user to verify first', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('ai-assistants.edit'))
        ->assertRedirect(route('verification.notice'));
});

it('no longer sends connected apps to the security page', function () {
    $user = User::factory()->create();
    connectedAppToken($user, oauthApp('Claude'));

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->missing('connectedApps'));
});
