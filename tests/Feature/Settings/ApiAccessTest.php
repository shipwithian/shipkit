<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The security page as the user sees it, after confirming their password.
 */
function securityPage(User $user): Assert
{
    $page = null;

    test()->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(function (Assert $assert) use (&$page): void {
            $page = $assert;
        });

    return $page;
}

describe('personal access tokens', function () {
    it('rejects guests', function () {
        $this->post(route('personal-access-tokens.store'), ['name' => 'Deploy'])->assertRedirect(route('login'));
    });

    it('creates a token and shows its secret once', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->post(route('personal-access-tokens.store'), ['name' => 'Deploy script'])
            ->assertRedirect(route('security.edit'));

        $flash = $response->getSession()->get('inertia.flash_data');
        $secret = $flash['personalAccessToken']['secret'] ?? null;

        expect($flash['personalAccessToken']['name'])->toBe('Deploy script')
            ->and($secret)->toBeString();

        securityPage($user)
            ->has('personalAccessTokens', 1)
            ->where('personalAccessTokens.0.name', 'Deploy script')
            ->missing('personalAccessTokens.0.secret');

        Auth::forgetGuards();

        $this->withToken($secret)->getJson('/api/v1/user')->assertOk();
    });

    it('requires a name', function () {
        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('personal-access-tokens.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    });

    it('lists the user\'s working tokens, not other users\' or connected apps\'', function () {
        $user = User::factory()->create();
        $user->createToken('Laptop', ['api']);
        $user->createToken('Old script', ['api'])->token->revoke();
        User::factory()->create()->createToken('Stranger', ['api']);
        connectedAppToken($user);

        securityPage($user)
            ->has('personalAccessTokens', 1)
            ->where('personalAccessTokens.0.name', 'Laptop');
    });

    it('revokes a token so it stops working', function () {
        $user = User::factory()->create();
        $token = $user->createToken('Laptop', ['api']);

        $this->actingAs($user)
            ->delete(route('personal-access-tokens.destroy', $token->token->id))
            ->assertRedirect();

        Auth::forgetGuards();

        $this->withToken($token->accessToken)->getJson('/api/v1/user')->assertUnauthorized();
    });

    it('does not reveal or revoke another user\'s token', function () {
        $token = User::factory()->create()->createToken('Victim', ['api']);

        $this->actingAs(User::factory()->create())
            ->delete(route('personal-access-tokens.destroy', $token->token->id))
            ->assertNotFound();

        expect($token->token->refresh()->revoked)->toBeFalse();
    });
});

describe('connected apps', function () {
    it('lists only the apps the user approved', function () {
        $user = User::factory()->create();
        connectedAppToken($user, oauthApp('Calendar Sync'));
        oauthApp('Never Approved');
        connectedAppToken(User::factory()->create(), oauthApp('Someone Else\'s App'));
        $user->createToken('Laptop', ['api']);

        securityPage($user)
            ->has('connectedApps', 1)
            ->where('connectedApps.0.name', 'Calendar Sync');
    });

    it('disconnects one app, leaving the others working', function () {
        $user = User::factory()->create();
        $calendar = oauthApp('Calendar Sync');
        $calendarToken = connectedAppToken($user, $calendar);
        $notesToken = connectedAppToken($user, oauthApp('Notes'));

        $this->actingAs($user)
            ->delete(route('connected-apps.destroy', $calendar->getKey()))
            ->assertRedirect();

        expect($calendarToken->refresh()->revoked)->toBeTrue()
            ->and($calendarToken->refreshToken->revoked)->toBeTrue()
            ->and($notesToken->refresh()->revoked)->toBeFalse();

        securityPage($user)
            ->has('connectedApps', 1)
            ->where('connectedApps.0.name', 'Notes');
    });

    it('does not reveal or disconnect another user\'s app', function () {
        $app = oauthApp();
        $token = connectedAppToken(User::factory()->create(), $app);

        $this->actingAs(User::factory()->create())
            ->delete(route('connected-apps.destroy', $app->getKey()))
            ->assertNotFound();

        expect($token->refresh()->revoked)->toBeFalse();
    });
});

it('removes every token and connected app when the account is deleted', function () {
    $user = User::factory()->create();
    $user->createToken('Laptop', ['api']);
    $appToken = connectedAppToken($user);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect();

    expect($user->tokens()->count())->toBe(0)
        ->and($appToken->refreshToken()->exists())->toBeFalse();
});
