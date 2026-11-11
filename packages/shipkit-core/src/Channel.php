<?php

namespace ShipKit\Core;

/**
 * Where a change came from: the web app, the REST API, or an AI client over MCP, with the OAuth
 * client that made it. The delivery layer builds it and passes it, after `$actor`, to the Actions
 * that record it; Actions never read the request to find it.
 */
final readonly class Channel
{
    public const string WEB = 'web';

    public const string API = 'api';

    public const string MCP = 'mcp';

    private function __construct(
        public string $kind,
        public ?string $clientId = null,
        public ?string $clientName = null,
    ) {}

    public static function web(): self
    {
        return new self(self::WEB);
    }

    public static function api(?string $clientId = null, ?string $clientName = null): self
    {
        return new self(self::API, $clientId, $clientName);
    }

    public static function mcp(string $clientId, string $clientName): self
    {
        return new self(self::MCP, $clientId, $clientName);
    }

    /**
     * A short label for support screens and logs, such as "MCP (Claude)".
     */
    public function label(): string
    {
        $kind = $this->kind === self::WEB ? 'Web' : strtoupper($this->kind);

        return $this->clientName === null ? $kind : "{$kind} ({$this->clientName})";
    }

    /**
     * @return array{kind: string, client_id: string|null, client_name: string|null}
     */
    public function toContext(): array
    {
        return [
            'kind' => $this->kind,
            'client_id' => $this->clientId,
            'client_name' => $this->clientName,
        ];
    }
}
