<?php

use App\Mcp\Concerns\ReportsDomainExceptions;
use App\Mcp\Support\ConsequentialActionConfirmation;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use ShipKit\Core\Exceptions\DomainException;

/**
 * A broken business rule, as a feature would throw from a model guard or an Action.
 */
class CoffeeIsSoldOut extends DomainException
{
    public function field(): string
    {
        return 'item';
    }
}

/**
 * A consequential tool built the documented way: it confirms before acting and reports broken rules.
 */
#[Name('place_test_order')]
#[IsDestructive]
class PlaceTestOrderTool extends Tool
{
    use ReportsDomainExceptions;

    /** @var list<array<string, mixed>> */
    public static array $placed = [];

    /** Runs inside the action, to act while the first confirmed call is still running. */
    public static ?Closure $duringAct = null;

    public function handle(Request $request, ConsequentialActionConfirmation $confirmation): Response|ResponseFactory
    {
        $validated = $request->validate(['item' => 'required|string', 'quantity' => 'required|integer|min:1']);

        return $this->reportingDomainExceptions(fn (): Response|ResponseFactory => $confirmation->confirmBeforeActing(
            $request,
            static::class,
            $validated,
            fn (): string => "Order {$validated['quantity']} × {$validated['item']}.",
            function () use ($validated): array {
                if ($validated['item'] === 'sold-out coffee') {
                    throw new CoffeeIsSoldOut('This coffee is sold out today.');
                }

                if (self::$duringAct !== null) {
                    (self::$duringAct)();
                }

                self::$placed[] = $validated;

                return ['order_number' => count(self::$placed)];
            },
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return ['confirmed' => $schema->boolean()->required()];
    }
}

/**
 * A second consequential tool, to prove a token for one tool never confirms another.
 */
#[Name('cancel_test_order')]
#[IsDestructive]
class CancelTestOrderTool extends PlaceTestOrderTool {}

class ConsequentialTestServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [PlaceTestOrderTool::class, CancelTestOrderTool::class];
}

beforeEach(function () {
    PlaceTestOrderTool::$placed = [];
    PlaceTestOrderTool::$duringAct = null;
    $this->customer = User::factory()->create();
});

/**
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function callTestTool(User $user, array $arguments, string $tool = PlaceTestOrderTool::class): TestResponse
{
    return ConsequentialTestServer::actingAs($user)->tool($tool, $arguments);
}

const FLAT_WHITE = ['item' => 'flat white', 'quantity' => 2];

it('returns a summary and a token without acting on the first call', function () {
    callTestTool($this->customer, FLAT_WHITE)
        ->assertOk()
        ->assertStructuredContentPath('confirmed', false)
        ->assertStructuredContentPath('summary', 'Order 2 × flat white.')
        ->assertStructuredContentPath('expires_in_minutes', 10);

    expect(PlaceTestOrderTool::$placed)->toBe([]);
});

it('acts once when confirmed', function () {
    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token])
        ->assertOk()
        ->assertExactStructuredContent(['confirmed' => true, 'order_number' => 1]);

    expect(PlaceTestOrderTool::$placed)->toHaveCount(1);
});

it('returns the first result without acting again when the confirmation is repeated', function () {
    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token]);

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token])
        ->assertExactStructuredContent(['confirmed' => true, 'order_number' => 1]);

    expect(PlaceTestOrderTool::$placed)->toHaveCount(1);
});

it('rejects a token for different details', function () {
    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));

    callTestTool($this->customer, ['item' => 'flat white', 'quantity' => 20, 'confirmation_token' => $token])->assertHasErrors();

    expect(PlaceTestOrderTool::$placed)->toBe([]);
});

it('rejects a token that was already used when the details are different', function () {
    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token])->assertOk();

    callTestTool($this->customer, ['item' => 'flat white', 'quantity' => 20, 'confirmation_token' => $token])->assertHasErrors();

    expect(PlaceTestOrderTool::$placed)->toHaveCount(1);
});

it('rejects a token issued to another user', function () {
    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));

    callTestTool(User::factory()->create(), [...FLAT_WHITE, 'confirmation_token' => $token])->assertHasErrors();

    expect(PlaceTestOrderTool::$placed)->toBe([]);
});

it('rejects a token issued for another tool', function () {
    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token], CancelTestOrderTool::class)->assertHasErrors();

    expect(PlaceTestOrderTool::$placed)->toBe([]);
});

it('rejects a forged token', function () {
    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => 'forged'])->assertHasErrors();

    expect(PlaceTestOrderTool::$placed)->toBe([]);
});

it('rejects an expired token', function () {
    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));

    $this->travel(11)->minutes();

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token])->assertHasErrors();

    expect(PlaceTestOrderTool::$placed)->toBe([]);
});

it('reports a broken business rule as field: message, not an internal error', function () {
    $arguments = ['item' => 'sold-out coffee', 'quantity' => 1];
    $token = confirmationTokenFrom(callTestTool($this->customer, $arguments));

    callTestTool($this->customer, [...$arguments, 'confirmation_token' => $token])
        ->assertHasErrors(['item: This coffee is sold out today.']);
});

it('answers "still being processed" when a repeated confirmation arrives while the first is running', function () {
    config(['mcp.confirmation_lock_wait_seconds' => 0]);

    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));
    $nonce = json_decode(Crypt::decryptString($token), true)['nonce'];

    $running = Cache::lock("mcp-confirmation:{$nonce}:lock", 60);
    $running->get();

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token])
        ->assertHasErrors(['This action is still being processed. Call again in a moment with the same confirmation_token.']);

    expect(PlaceTestOrderTool::$placed)->toBe([]);

    $running->release();

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token])
        ->assertExactStructuredContent(['confirmed' => true, 'order_number' => 1]);
});

it('keeps the lock past ten seconds, so a slow action never runs twice', function () {
    config(['mcp.confirmation_lock_wait_seconds' => 0]);

    $token = confirmationTokenFrom(callTestTool($this->customer, FLAT_WHITE));
    $repeated = null;

    PlaceTestOrderTool::$duringAct = function () use ($token, &$repeated): void {
        $this->travel(30)->seconds();

        $repeated = callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token]);
    };

    callTestTool($this->customer, [...FLAT_WHITE, 'confirmation_token' => $token])->assertOk();

    $repeated->assertHasErrors(['This action is still being processed. Call again in a moment with the same confirmation_token.']);

    expect(PlaceTestOrderTool::$placed)->toHaveCount(1);
});
