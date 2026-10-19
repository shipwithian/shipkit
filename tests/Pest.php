<?php

use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ShipKit\Core\AccessControl\SystemPermission;
use ShipKit\Core\AccessControl\SystemRole;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Protected records are created only by ShipKit's access-control bootstrap, so tests get them by
 * running the same seeder the application runs.
 */
function seedProtectedRecords(): void
{
    if (! Role::query()->where('name', SystemRole::SuperAdmin->value)->exists()) {
        test()->seed(AccessControlSeeder::class);
    }
}

function protectedRole(): Role
{
    seedProtectedRecords();

    return Role::query()->where('name', SystemRole::SuperAdmin->value)->firstOrFail();
}

function protectedPermission(): Permission
{
    seedProtectedRecords();

    return Permission::query()->where('name', SystemPermission::ManageRolesResource->value)->firstOrFail();
}

function protectedUser(): User
{
    return protectedRole()->users()->firstOrFail();
}

/**
 * @param  array<int, string>  $permissions
 */
function userWithPermissions(array $permissions): User
{
    if (array_intersect($permissions, array_column(SystemPermission::cases(), 'value')) !== []) {
        seedProtectedRecords();
    }

    $role = Role::create([
        'name' => 'test-role-'.Str::uuid(),
        'guard_name' => 'web',
    ]);

    $role->givePermissionTo(
        collect($permissions)->map(
            fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
        ),
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}
