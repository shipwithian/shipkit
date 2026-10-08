<?php

use App\Actions\Settings\DeleteAccountAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('deletes an ordinary account', function () {
    $user = User::factory()->create();

    app(DeleteAccountAction::class)->handle($user);

    $this->assertModelMissing($user);
});

it('refuses to delete a protected account for any caller', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_protected' => true])->save();

    expect(fn () => app(DeleteAccountAction::class)->handle($user))
        ->toThrow(ValidationException::class);

    $this->assertModelExists($user);
});
