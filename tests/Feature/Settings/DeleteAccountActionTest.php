<?php

use App\Domain\Settings\Actions\DeleteAccountAction;
use App\Domain\Settings\Exceptions\ProtectedAccountCannotBeDeleted;
use App\Domain\Shared\Exceptions\BulkWriteBypassesInvariants;
use App\Models\User;

it('deletes an ordinary account', function () {
    $user = User::factory()->create();

    app(DeleteAccountAction::class)->handle($user);

    $this->assertModelMissing($user);
});

it('refuses to delete a protected account for any caller', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_protected' => true])->save();

    expect(fn () => app(DeleteAccountAction::class)->handle($user))
        ->toThrow(ProtectedAccountCannotBeDeleted::class);

    $this->assertModelExists($user);
});

it('refuses to delete a protected account through the model and keeps its roles', function () {
    $user = userWithPermissions(['edit posts']);
    $user->forceFill(['is_protected' => true])->save();

    expect(fn () => $user->delete())->toThrow(ProtectedAccountCannotBeDeleted::class);

    $this->assertModelExists($user);
    expect($user->fresh()->roles)->toHaveCount(1);
});

it('refuses to bulk delete users past the account guards', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_protected' => true])->save();

    expect(fn () => User::query()->whereKey($user->id)->delete())
        ->toThrow(BulkWriteBypassesInvariants::class);

    $this->assertModelExists($user);
});

it('refuses to delete a protected account even with model events silenced', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_protected' => true])->save();

    expect(fn () => $user->deleteQuietly())->toThrow(ProtectedAccountCannotBeDeleted::class);

    $this->assertModelExists($user);
});
