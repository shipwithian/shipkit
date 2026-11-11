<?php

namespace App\Mcp\Concerns;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * The confirmation step every consequential tool (one marked `#[IsDestructive]`) uses: type-hint it
 * in the tool's `handle()` and run the action through `confirmBeforeActing()`.
 *
 * The first call returns a summary and a `confirmation_token` and changes nothing. The call with
 * the token acts. The token is encrypted, lasts ten minutes, is bound to this tool, the exact
 * arguments, and the user, and is single-use: repeating the confirmed call returns the first
 * result without acting again, so the token is also the idempotency key.
 *
 * This confirms what the model saw. The human gate is the AI client's own approval prompt for
 * destructive tools.
 */
final class ConsequentialActionConfirmation
{
    public const int CONFIRMATION_MINUTES = 10;

    /**
     * @param  class-string  $tool  the tool asking, so a token for one tool never confirms another
     * @param  array<string, mixed>  $arguments  the validated arguments the action will use
     * @param  Closure(): string  $summary  what will happen, in words the user can approve
     * @param  Closure(): array<string, mixed>  $act  perform the action and return its structured result
     */
    public function confirmBeforeActing(Request $request, string $tool, array $arguments, Closure $summary, Closure $act): Response|ResponseFactory
    {
        $token = $request->get('confirmation_token');

        if (! is_string($token) || $token === '') {
            return Response::structured([
                'confirmed' => false,
                'summary' => $summary(),
                'confirmation_token' => $this->issueConfirmationToken($request, $tool, $arguments),
                'expires_in_minutes' => self::CONFIRMATION_MINUTES,
            ]);
        }

        $nonce = $this->redeemConfirmationToken($request, $tool, $arguments, $token);

        $lock = Cache::lock("mcp-confirmation:{$nonce}:lock", self::CONFIRMATION_MINUTES * 60);

        try {
            return $lock->block((int) config('mcp.confirmation_lock_wait_seconds', 5), function () use ($nonce, $act): ResponseFactory {
                $resultKey = "mcp-confirmation:{$nonce}:result";

                /** @var array<string, mixed>|null $result */
                $result = Cache::get($resultKey);

                if ($result === null) {
                    $result = ['confirmed' => true, ...$act()];
                    Cache::put($resultKey, $result, now()->addMinutes(self::CONFIRMATION_MINUTES));
                }

                return Response::structured($result);
            });
        } catch (LockTimeoutException) {
            return Response::error('This action is still being processed. Call again in a moment with the same confirmation_token.');
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function issueConfirmationToken(Request $request, string $tool, array $arguments): string
    {
        return Crypt::encryptString((string) json_encode([
            'nonce' => (string) Str::uuid(),
            'tool' => $tool,
            'user' => $request->user()?->getAuthIdentifier(),
            'arguments' => $this->fingerprint($arguments),
            'expires_at' => now()->addMinutes(self::CONFIRMATION_MINUTES)->getTimestamp(),
        ]));
    }

    /**
     * Check the token belongs to this tool, these arguments, and this user, and has not expired.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function redeemConfirmationToken(Request $request, string $tool, array $arguments, string $token): string
    {
        try {
            /** @var array{nonce?: string, tool?: string, user?: int|string|null, arguments?: string, expires_at?: int} $claims */
            $claims = (array) json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            $claims = [];
        }

        $valid = ($claims['tool'] ?? null) === $tool
            && ($claims['user'] ?? null) === $request->user()?->getAuthIdentifier()
            && ($claims['arguments'] ?? null) === $this->fingerprint($arguments)
            && ($claims['expires_at'] ?? 0) >= now()->getTimestamp()
            && is_string($claims['nonce'] ?? null);

        if (! $valid) {
            throw ValidationException::withMessages([
                'confirmation_token' => 'This confirmation_token is invalid, expired, or was issued for different details. Call the tool again without a token to get a new summary, show it to the user, and confirm with the new token.',
            ]);
        }

        return (string) $claims['nonce'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function fingerprint(array $arguments): string
    {
        unset($arguments['confirmation_token']);
        ksort($arguments);

        return hash('sha256', (string) json_encode($arguments));
    }
}
