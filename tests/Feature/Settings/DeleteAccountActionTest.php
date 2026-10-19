<?php

use App\Domain\Settings\Actions\DeleteAccountAction;
use App\Domain\Settings\Exceptions\ProtectedAccountCannotBeDeleted;
use App\Models\User;
use ShipKit\Core\Exceptions\BulkWriteBypassesInvariants;

it('deletes an ordinary account', function () {
    $user = User::factory()->create();

    app(DeleteAccountAction::class)->handle($user);

    $this->assertModelMissing($user);
});

it('refuses to delete a protected account for any caller', function () {
    $user = protectedUser();

    expect(fn () => app(DeleteAccountAction::class)->handle($user))
        ->toThrow(ProtectedAccountCannotBeDeleted::class);

    $this->assertModelExists($user);
});

it('refuses to delete a protected account through the model and keeps its roles', function () {
    $user = protectedUser();

    expect(fn () => $user->delete())->toThrow(ProtectedAccountCannotBeDeleted::class);

    $this->assertModelExists($user);
    expect($user->fresh()->roles)->toHaveCount(1);
});

it('refuses to bulk delete users past the account guards', function () {
    $user = protectedUser();

    expect(fn () => User::query()->whereKey($user->id)->delete())
        ->toThrow(BulkWriteBypassesInvariants::class);

    $this->assertModelExists($user);
});

it('refuses to delete a protected account even with model events silenced', function () {
    $user = protectedUser();

    expect(fn () => $user->deleteQuietly())->toThrow(ProtectedAccountCannotBeDeleted::class);

    $this->assertModelExists($user);
});
