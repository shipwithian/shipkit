<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the landing page for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user', null));
});

it('renders the landing page for a signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user.id', $user->id));
});
