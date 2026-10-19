<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase;
use ShipKit\Core\Exceptions\GuardedModelIsMisconfigured;
use ShipKit\Core\GuardedModelMap;
use ShipKit\Core\ShipKitServiceProvider;

uses(TestCase::class);

/**
 * A model that skips GuardsInvariants, so its invariants could be bypassed.
 */
class UnguardedMappedUser extends Model {}

it('resolves each mapped guarded model', function () {
    $models = app(GuardedModelMap::class);

    expect($models->role())->toBe(config('shipkit.models.role'))
        ->and($models->permission())->toBe(config('shipkit.models.permission'))
        ->and($models->user())->toBe(config('shipkit.models.user'));
});

it('refuses to boot when a mapped model does not use GuardsInvariants', function () {
    config(['shipkit.models.user' => UnguardedMappedUser::class]);

    expect(fn () => (new ShipKitServiceProvider(app()))->boot())
        ->toThrow(GuardedModelIsMisconfigured::class, UnguardedMappedUser::class);
});

it('refuses to boot when a mapped model does not exist', function () {
    config(['shipkit.models.role' => 'App\Models\MissingRole']);

    expect(fn () => (new ShipKitServiceProvider(app()))->boot())
        ->toThrow(GuardedModelIsMisconfigured::class, 'App\Models\MissingRole');
});

it('names the unmapped model when asked for it', function () {
    config(['shipkit.models.permission' => null]);

    expect(fn () => app(GuardedModelMap::class)->permission())
        ->toThrow(GuardedModelIsMisconfigured::class, 'shipkit.models.permission');
});
