<?php

namespace ShipKit\Core;

/**
 * Where a change came from: the web app, the REST API, or an AI client over MCP, with the OAuth
 * client that made it. The delivery layer builds it and passes it, after `$actor`, to the Actions
 * that record it; Actions never read the request to find it.
 *
 * The client id is trustworthy. The client name is whatever the client called itself when it
 * registered, so use it to read a log, never to decide anything.
 */
final readonly class Channel
{
    private function __construct(
        public ChannelKind $kind,
        public ?string $clientId = null,
        public ?string $clientName = null,
    ) {}

    public static function web(): self
    {
        return new self(ChannelKind::Web);
    }

    public static function api(?string $clientId = null, ?string $clientName = null): self
    {
        return new self(ChannelKind::Api, $clientId, $clientName);
    }

    public static function mcp(string $clientId, ?string $clientName = null): self
    {
        return new self(ChannelKind::Mcp, $clientId, $clientName);
    }

    /**
     * Rebuild a channel from what `toContext()` shared, such as Laravel's `Context`.
     *
     * @param  array{kind?: string, client_id?: string|null, client_name?: string|null}  $context
     */
    public static function fromContext(array $context): self
    {
        return new self(
            ChannelKind::tryFrom($context['kind'] ?? '') ?? ChannelKind::Web,
            $context['client_id'] ?? null,
            $context['client_name'] ?? null,
        );
    }

    public function withClientName(?string $clientName): self
    {
        return new self($this->kind, $this->clientId, $clientName);
    }

    /**
     * A short label for logs and support screens, such as "MCP (Claude)".
     */
    public function label(): string
    {
        $kind = match ($this->kind) {
            ChannelKind::Web => 'Web',
            ChannelKind::Api => 'API',
            ChannelKind::Mcp => 'MCP',
        };

        return $this->clientName === null ? $kind : "{$kind} ({$this->clientName})";
    }

    /**
     * @return array{kind: string, client_id: string|null, client_name: string|null}
     */
    public function toContext(): array
    {
        return [
            'kind' => $this->kind->value,
            'client_id' => $this->clientId,
            'client_name' => $this->clientName,
        ];
    }
}
