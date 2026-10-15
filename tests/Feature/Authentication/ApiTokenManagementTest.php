<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

describe('index', function () {
    it('rejects guests', function () {
        $this->getJson('/api/v1/tokens')->assertUnauthorized();
    });

    it('lists only the caller\'s tokens without secrets', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $current = $user->createToken('laptop', ['*'], now()->addDay());
        $user->createToken('phone', ['*']);
        $other->createToken('stranger', ['*']);

        $current->accessToken->forceFill(['last_used_at' => now()->subHour()])->save();

        $response = $this->withToken($current->plainTextToken)
            ->getJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'created_at', 'last_used_at', 'expires_at']]])
            ->assertJsonMissing(['name' => 'stranger']);

        expect(collect($response->json('data'))->pluck('name')->all())->toEqualCanonicalizing(['laptop', 'phone']);
        expect($response->json('data.0'))->not->toHaveKeys(['token', 'abilities']);
        expect($response->getContent())->not->toContain($current->plainTextToken);
    });
});

describe('store', function () {
    it('rejects guests', function () {
        $this->postJson('/api/v1/tokens', ['name' => 'CI'])->assertUnauthorized();
    });

    it('requires a name', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tokens', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    it('creates a token and shows the secret once', function () {
        $user = User::factory()->create();
        $bearer = $user->createToken('existing', ['*'])->plainTextToken;

        $response = $this->withToken($bearer)
            ->postJson('/api/v1/tokens', ['name' => 'CI'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'CI')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['id', 'name', 'created_at', 'last_used_at', 'expires_at'], 'token']);

        $secret = $response->json('token');
        $stored = PersonalAccessToken::query()->findOrFail($response->json('data.id'));

        expect($stored->tokenable_id)->toBe($user->id)
            ->and($stored->name)->toBe('CI')
            ->and($stored->token)->not->toBe($secret);

        Auth::forgetGuards();

        $this->withToken($secret)->getJson('/api/v1/user')->assertOk();

        $list = $this->withToken($bearer)->getJson('/api/v1/tokens')->assertOk();
        expect($list->getContent())->not->toContain($secret);
    });
});

describe('destroy', function () {
    it('rejects guests', function () {
        $this->deleteJson('/api/v1/tokens/1')->assertUnauthorized();
    });

    it('does not reveal or revoke another user\'s token', function () {
        $user = User::factory()->create();
        $victim = User::factory()->create()->createToken('victim', ['*']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/tokens/{$victim->accessToken->id}")
            ->assertNotFound();

        expect(PersonalAccessToken::query()->find($victim->accessToken->id))->not->toBeNull();
    });

    it('revokes a token issued to another device', function () {
        $user = User::factory()->create();
        $current = $user->createToken('laptop', ['*']);
        $phone = $user->createToken('phone', ['*']);

        $this->withToken($current->plainTextToken)
            ->deleteJson("/api/v1/tokens/{$phone->accessToken->id}")
            ->assertNoContent();

        expect(PersonalAccessToken::query()->find($phone->accessToken->id))->toBeNull()
            ->and(PersonalAccessToken::query()->find($current->accessToken->id))->not->toBeNull();

        Auth::forgetGuards();

        $this->withToken($phone->plainTextToken)->getJson('/api/v1/user')->assertUnauthorized();
    });

    it('can revoke the token making the request', function () {
        $user = User::factory()->create();
        $current = $user->createToken('laptop', ['*']);

        $this->withToken($current->plainTextToken)
            ->deleteJson("/api/v1/tokens/{$current->accessToken->id}")
            ->assertNoContent();

        expect($user->tokens()->count())->toBe(0);
    });

    it('returns not found for a missing token', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/tokens/999999')->assertNotFound();
    });
});
