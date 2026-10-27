<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use ShipKit\Core\AccessControl\BootstrapAccessControlAction;
use ShipKit\Core\AccessControl\SystemPermission;
use ShipKit\Core\AccessControl\SystemRecordIsNotProtected;
use ShipKit\Core\AccessControl\SystemRole;
use ShipKit\Core\GuardedModelMap;

uses(TestCase::class, RefreshDatabase::class);

function bootstrapAccessControl(): void
{
    app(BootstrapAccessControlAction::class)->handle('Site Administrator', 'admin@example.test', 'secure-password');
}

function systemRecord(string $key, string $column, string $value): ?Model
{
    $model = app(GuardedModelMap::class)->{$key}();

    return $model::query()->where($column, $value)->first();
}

it('creates the protected system records on a fresh database', function () {
    bootstrapAccessControl();

    $role = systemRecord('role', 'name', SystemRole::SuperAdmin->value);
    $user = systemRecord('user', 'email', 'admin@example.test');

    expect($role->is_protected)->toBeTrue()
        ->and($role->guard_name)->toBe('web')
        ->and($user->is_protected)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole(SystemRole::SuperAdmin->value))->toBeTrue();

    foreach (SystemPermission::cases() as $permission) {
        expect(systemRecord('permission', 'name', $permission->value)?->is_protected)->toBeTrue()
            ->and($role->hasPermissionTo($permission->value))->toBeTrue();
    }
});

it('changes nothing when run again', function () {
    bootstrapAccessControl();
    $user = systemRecord('user', 'email', 'admin@example.test');
    $password = $user->password;

    bootstrapAccessControl();

    $models = app(GuardedModelMap::class);

    expect($models->role()::query()->count())->toBe(1)
        ->and($models->permission()::query()->count())->toBe(count(SystemPermission::cases()))
        ->and($models->user()::query()->count())->toBe(1)
        ->and($user->fresh()->password)->toBe($password);
});

it('stops when a system record exists without protection, and creates nothing', function (string $key, Closure $createUnprotected) {
    $createUnprotected();
    $models = app(GuardedModelMap::class);
    $before = [$models->role()::query()->count(), $models->permission()::query()->count(), $models->user()::query()->count()];

    expect(fn () => bootstrapAccessControl())->toThrow(SystemRecordIsNotProtected::class);

    expect([$models->role()::query()->count(), $models->permission()::query()->count(), $models->user()::query()->count()])->toBe($before);
})->with([
    'a system permission' => ['permission', fn () => app(GuardedModelMap::class)->permission()::query()->create(['name' => SystemPermission::AssignPermissions->value, 'guard_name' => 'web'])],
    'the super-admin role' => ['role', fn () => app(GuardedModelMap::class)->role()::query()->create(['name' => SystemRole::SuperAdmin->value, 'guard_name' => 'web'])],
    'the super-admin user' => ['user', fn () => app(GuardedModelMap::class)->user()::query()->create(['name' => 'Someone', 'email' => 'admin@example.test', 'password' => 'secret'])],
]);

it('reserves SystemPermission for permissions that keep access control recoverable', function () {
    $reserved = [
        'manage roles resource' => 'create, rename, and delete roles',
        'manage permissions resource' => 'create, rename, and delete permissions',
        'assign permissions' => 'put permissions on roles',
        'assign roles' => 'put roles on users',
    ];

    expect(array_column(SystemPermission::cases(), 'value'))->toEqualCanonicalizing(array_keys($reserved), 'SystemPermission is reserved for permissions without which nobody could recover or change who may do what. A feature permission, even an access-control one such as viewing an audit log, belongs in its feature seeder. If this one genuinely qualifies, add it to $reserved in this test with the reason.');
});
