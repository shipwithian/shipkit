<?php

test('the current API user includes current permissions without exposing secrets', function () {
    $user = userWithPermissions(['reports.view']);
    $token = $user->createToken('test client', ['*'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.permissions', ['reports.view'])
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.two_factor_secret')
        ->assertJsonMissingPath('data.two_factor_recovery_codes')
        ->assertJsonMissingPath('data.token');
});
