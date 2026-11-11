<?php

use App\Enums\ApiScope;
use App\Models\OAuthClient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
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

/**
 * Authenticate the next API requests as the user, with a token carrying the `api` scope.
 *
 * @return TestCase
 */
function actingAsApi(User $user): object
{
    Passport::actingAs($user, [ApiScope::Api->value]);

    return test();
}

/**
 * An app that signs users in through the authorization-code flow, created the way `passport:client --public` creates one.
 */
function oauthApp(string $name = 'Partner App'): OAuthClient
{
    /** @var OAuthClient */
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, ['https://partner.test/callback'], confidential: false);
}

/**
 * An access token, with its refresh token, that the user granted an app through the consent screen.
 */
function connectedAppToken(User $user, ?OAuthClient $app = null): Token
{
    $app ??= oauthApp();

    /** @var Token $token */
    $token = Passport::token()->newQuery()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => $app->getKey(),
        'scopes' => [ApiScope::Api->value],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    Passport::refreshToken()->newQuery()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $token->getKey(),
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);

    return $token;
}
