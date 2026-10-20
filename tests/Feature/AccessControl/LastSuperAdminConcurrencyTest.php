<?php

use App\Domain\AccessControl\Exceptions\LastSuperAdminCannotBeRemoved;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use ShipKit\Core\AccessControl\SystemRole;

/*
 * Proves that `Role::ensureCanLoseHolder()` serializes two removals of the last two super admins.
 *
 * SQLite ignores `FOR UPDATE`, so this group runs only on MySQL and Postgres: phpunit.xml excludes
 * it, and the `concurrency` job in .github/workflows/tests.yml runs it with `--group=concurrency`.
 *
 * Each case interleaves two connections in a fixed order on one process. The holder removes one
 * super admin inside an open transaction; the contender, with a short lock timeout, tries to remove
 * the other and must time out on the holder's lock; the holder commits, and the contender's retry
 * is refused by the guard. The timeout only bounds the wait: the contender cannot succeed while the
 * holder's transaction is open, so the outcome never depends on timing.
 */

pest()->group('concurrency');

beforeEach(function () {
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
        $this->markTestSkipped('Row locks need MySQL or Postgres: run with DB_CONNECTION=mysql or DB_CONNECTION=pgsql.');
    }

    $connection = config('database.connections.'.config('database.default'));

    config([
        'database.connections.holder' => $connection,
        'database.connections.contender' => $connection,
    ]);

    DB::connection('contender')->statement(DB::connection('contender')->getDriverName() === 'pgsql'
        ? "set lock_timeout = '1s'"
        : 'set session innodb_lock_wait_timeout = 1');
});

afterEach(function () {
    // Disconnecting rolls back an open transaction and releases its locks.
    DB::purge('holder');
    DB::purge('contender');

    // The cases commit through their own connections, which RefreshDatabase cannot roll back.
    RefreshDatabaseState::$migrated = false;
});

it('lets only one of two concurrent removals take the last two super admins', function (Closure $holderRemoves, Closure $contenderRemoves) {
    [$first, $second] = onConnection('holder', twoSuperAdmins(...));

    onConnection('holder', function () use ($holderRemoves, $first) {
        DB::beginTransaction();
        $holderRemoves(User::query()->findOrFail($first));
    });

    expectLockTimeout(fn () => onConnection('contender', fn () => $contenderRemoves(User::query()->findOrFail($second))));

    onConnection('holder', fn () => DB::commit());

    expect(fn () => onConnection('contender', fn () => $contenderRemoves(User::query()->findOrFail($second))))
        ->toThrow(LastSuperAdminCannotBeRemoved::class);

    expect(onConnection('contender', fn () => superAdminIds()))->toBe([$second]);
})->with([
    'holder loses the role' => fn (User $user) => $user->removeRole(SystemRole::SuperAdmin->value),
    'holder is deleted' => fn (User $user) => $user->delete(),
])->with([
    'contender loses the role' => fn (User $user) => $user->removeRole(SystemRole::SuperAdmin->value),
    'contender is deleted' => fn (User $user) => $user->delete(),
]);

/**
 * Run the callback with the given connection as the default, so models and the transaction that
 * `GuardsInvariants` opens both use it.
 *
 * @template TResult
 *
 * @param  Closure(): TResult  $callback
 * @return TResult
 */
function onConnection(string $connection, Closure $callback): mixed
{
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection($connection);

    try {
        return $callback();
    } finally {
        DB::setDefaultConnection($default);
    }
}

/**
 * Two removable super admins. The bootstrap's super admin is protected and cannot be deleted, so
 * the guard, which recognises the role by name, is given an unprotected role with two holders.
 *
 * @return array{int, int}
 */
function twoSuperAdmins(): array
{
    $role = Role::create(['name' => SystemRole::SuperAdmin->value, 'guard_name' => 'web']);
    $users = User::factory()->count(2)->create();
    $users->each(fn (User $user) => $user->assignRole($role));

    return [$users[0]->getKey(), $users[1]->getKey()];
}

/**
 * @return list<int>
 */
function superAdminIds(): array
{
    return Role::findByName(SystemRole::SuperAdmin->value, 'web')->users()->pluck('id')->all();
}

/**
 * Assert the removal waited on another transaction's lock until the lock timeout cancelled it.
 */
function expectLockTimeout(Closure $removal): void
{
    try {
        $removal();
    } catch (QueryException $exception) {
        $mysqlLockWaitTimeout = ($exception->errorInfo[1] ?? null) === 1205;
        $postgresLockNotAvailable = $exception->getCode() === '55P03';

        expect($mysqlLockWaitTimeout || $postgresLockNotAvailable)->toBeTrue($exception->getMessage());

        return;
    }

    test()->fail('The second removal ran without waiting for the first one to commit, so both could pass the guard and remove every super admin.');
}
