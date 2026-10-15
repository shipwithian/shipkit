<?php

use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Domain\AccessControl\Models\RolePermission;
use App\Domain\Shared\Exceptions\BulkWriteBypassesInvariants;
use App\Domain\Shared\Exceptions\DomainException;

/**
 * A role table limited to two rows, to exercise a creation guard on a real guarded table.
 */
class CappedRole extends Role
{
    protected function guardCreate(): void
    {
        if (static::query()->count() >= 2) {
            throw new class extends DomainException
            {
                public function field(): string
                {
                    return 'role';
                }
            };
        }
    }
}

/**
 * A role-permission pivot that refuses every new row.
 */
class RefusingRolePermission extends RolePermission
{
    protected function guardCreate(): void
    {
        throw new class extends DomainException
        {
            public function field(): string
            {
                return 'permissions';
            }
        };
    }
}

function fillCappedRoles(): void
{
    CappedRole::create(['name' => 'one', 'guard_name' => 'web']);
    CappedRole::create(['name' => 'two', 'guard_name' => 'web']);
}

it('creates records up to the limit a guard allows', function () {
    fillCappedRoles();

    expect(Role::query()->count())->toBe(2);
});

it('refuses creation past the limit however the record is made', function (Closure $attempt) {
    fillCappedRoles();

    expect($attempt)->toThrow(DomainException::class);

    expect(Role::query()->count())->toBe(2);
})->with([
    'create()' => fn () => CappedRole::create(['name' => 'three', 'guard_name' => 'web']),
    'save()' => fn () => (new CappedRole(['name' => 'three', 'guard_name' => 'web']))->save(),
    'firstOrCreate()' => fn () => CappedRole::query()->firstOrCreate(['name' => 'three', 'guard_name' => 'web']),
    'createQuietly()' => fn () => CappedRole::createQuietly(['name' => 'three', 'guard_name' => 'web']),
    'with model events silenced' => fn () => CappedRole::withoutEvents(fn () => CappedRole::create(['name' => 'three', 'guard_name' => 'web'])),
]);

it('refuses bulk inserts that would skip the creation guard', function (Closure $attempt) {
    fillCappedRoles();

    expect($attempt)->toThrow(BulkWriteBypassesInvariants::class);

    expect(Role::query()->count())->toBe(2);
})->with([
    'insert()' => fn () => CappedRole::query()->insert([['name' => 'three', 'guard_name' => 'web']]),
    'insertGetId()' => fn () => CappedRole::query()->insertGetId(['name' => 'three', 'guard_name' => 'web']),
    'insertOrIgnore()' => fn () => CappedRole::query()->insertOrIgnore([['name' => 'three', 'guard_name' => 'web']]),
    'insertUsing()' => fn () => CappedRole::query()->insertUsing(['name', 'guard_name'], Role::query()->select('name', 'guard_name')),
    'upsert()' => fn () => CappedRole::query()->upsert([['name' => 'three', 'guard_name' => 'web']], ['name']),
]);

it('guards a pivot row created through attach', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $permission = Permission::findOrCreate('edit posts', 'web');

    expect(fn () => $role->permissions()->using(RefusingRolePermission::class)->attach($permission->id))
        ->toThrow(DomainException::class);

    expect($role->permissions()->count())->toBe(0);
});
