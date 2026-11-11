<?php

use App\Mcp\Concerns\ConsequentialActionConfirmation;
use App\Mcp\Concerns\ReportsDomainExceptions;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\Fluent\AssertableJson;
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

    public function handle(Request $request, ConsequentialActionConfirmation $confirmation): Response|ResponseFactory
    {
        $validated = $request->validate(['item' => 'required|string', 'quantity' => 'required|integer|min:1']);

        return $this->reportingDomainExceptions(fn (): Response|ResponseFactory => $confirmation->confirmBeforeActing(
            $request,
            self::class,
            $validated,
            fn (): string => "Order {$validated['quantity']} × {$validated['item']}.",
            function () use ($validated): array {
                if ($validated['item'] === 'sold-out coffee') {
                    throw new CoffeeIsSoldOut('This coffee is sold out today.');
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

class ConsequentialTestServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [PlaceTestOrderTool::class];
}

beforeEach(function () {
    PlaceTestOrderTool::$placed = [];
    $this->customer = User::factory()->create();
});

/**
 * @param  array<string, mixed>  $arguments
 */
function placeTestOrder(User $user, array $arguments): TestResponse
{
    return ConsequentialTestServer::actingAs($user)->tool(PlaceTestOrderTool::class, $arguments);
}

function confirmationTokenFor(User $user, array $arguments): string
{
    $token = null;

    placeTestOrder($user, $arguments)
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $content) use (&$token): void {
            $token = $content->toArray()['confirmation_token'];
            $content->etc();
        });

    return (string) $token;
}

it('returns a summary and a token without acting on the first call', function () {
    placeTestOrder($this->customer, ['item' => 'flat white', 'quantity' => 2])
        ->assertOk()
        ->assertStructuredContentPath('confirmed', false)
        ->assertStructuredContentPath('summary', 'Order 2 × flat white.')
        ->assertStructuredContentPath('expires_in_minutes', 10);

    expect(PlaceTestOrderTool::$placed)->toBe([]);
});

it('acts once when confirmed, and a repeated confirmation returns the first result', function () {
    $arguments = ['item' => 'flat white', 'quantity' => 2];
    $token = confirmationTokenFor($this->customer, $arguments);

    placeTestOrder($this->customer, [...$arguments, 'confirmation_token' => $token])
        ->assertOk()
        ->assertExactStructuredContent(['confirmed' => true, 'order_number' => 1]);

    placeTestOrder($this->customer, [...$arguments, 'confirmation_token' => $token])
        ->assertOk()
        ->assertExactStructuredContent(['confirmed' => true, 'order_number' => 1]);

    expect(PlaceTestOrderTool::$placed)->toHaveCount(1);
});

it('rejects a token for different details, another user, a forged token, or an expired one', function () {
    $token = confirmationTokenFor($this->customer, ['item' => 'flat white', 'quantity' => 2]);

    placeTestOrder($this->customer, ['item' => 'flat white', 'quantity' => 20, 'confirmation_token' => $token])->assertHasErrors();
    placeTestOrder(User::factory()->create(), ['item' => 'flat white', 'quantity' => 2, 'confirmation_token' => $token])->assertHasErrors();
    placeTestOrder($this->customer, ['item' => 'flat white', 'quantity' => 2, 'confirmation_token' => 'forged'])->assertHasErrors();

    $this->travel(11)->minutes();

    placeTestOrder($this->customer, ['item' => 'flat white', 'quantity' => 2, 'confirmation_token' => $token])->assertHasErrors();

    expect(PlaceTestOrderTool::$placed)->toBe([]);
});

it('reports a broken business rule as field: message, not an internal error', function () {
    $arguments = ['item' => 'sold-out coffee', 'quantity' => 1];
    $token = confirmationTokenFor($this->customer, $arguments);

    placeTestOrder($this->customer, [...$arguments, 'confirmation_token' => $token])
        ->assertHasErrors(['item: This coffee is sold out today.']);
});

it('answers "still being processed" when a repeated confirmation arrives while the first is running', function () {
    config(['mcp.confirmation_lock_wait_seconds' => 0]);

    $arguments = ['item' => 'flat white', 'quantity' => 2];
    $token = confirmationTokenFor($this->customer, $arguments);
    $nonce = json_decode(Crypt::decryptString($token), true)['nonce'];

    $running = Cache::lock("mcp-confirmation:{$nonce}:lock", 60);
    $running->get();

    placeTestOrder($this->customer, [...$arguments, 'confirmation_token' => $token])
        ->assertHasErrors(['This action is still being processed. Call again in a moment with the same confirmation_token.']);

    expect(PlaceTestOrderTool::$placed)->toBe([]);

    $running->release();

    placeTestOrder($this->customer, [...$arguments, 'confirmation_token' => $token])
        ->assertExactStructuredContent(['confirmed' => true, 'order_number' => 1]);
});
