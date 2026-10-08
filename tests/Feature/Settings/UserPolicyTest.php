<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('allows an ordinary user to delete their own account', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('delete', $user))->toBeTrue();
});

it('forbids deletion of a protected account', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_protected' => true])->save();

    expect(Gate::forUser($user)->allows('delete', $user))->toBeFalse();
});

it('forbids a user from deleting another account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    expect(Gate::forUser($user)->allows('delete', $otherUser))->toBeFalse();
});

it('allows a user to update their own account', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('update', $user))->toBeTrue();
});

it('forbids a user from updating another account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    expect(Gate::forUser($user)->allows('update', $otherUser))->toBeFalse();
});
