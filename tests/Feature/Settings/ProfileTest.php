<?php

use App\Models\User;

describe('edit', function () {
    it('displays the profile page', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    });
});

describe('update', function () {
    it('updates the profile and resets email verification when the email changes', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        expect($user->name)->toBe('Test User');
        expect($user->email)->toBe('test@example.com');
        expect($user->email_verified_at)->toBeNull();
    });

    it('keeps email verification when the email is unchanged', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        expect($user->refresh()->email_verified_at)->not->toBeNull();
    });
});

describe('destroy', function () {
    it('deletes the account and logs the user out', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        expect($user->fresh())->toBeNull();
    });

    it('requires the correct password', function () {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        expect($user->fresh())->not->toBeNull();
    });

    it('forbids protected users from deleting their account', function () {
        $user = User::factory()->create();
        $user->forceFill(['is_protected' => true])->save();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertForbidden();

        $this->assertModelExists($user);
    });
});
