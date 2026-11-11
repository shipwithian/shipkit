<?php

use ShipKit\Core\Channel;
use ShipKit\Core\ChannelKind;

it('describes where a change came from', function () {
    expect(Channel::web()->label())->toBe('Web')
        ->and(Channel::api()->label())->toBe('API')
        ->and(Channel::mcp('client-1')->label())->toBe('MCP')
        ->and(Channel::mcp('client-1', 'Claude')->label())->toBe('MCP (Claude)')
        ->and(Channel::mcp('client-1', 'Claude')->toContext())->toBe(['kind' => 'mcp', 'client_id' => 'client-1', 'client_name' => 'Claude']);
});

it('is rebuilt from what it shared', function () {
    $channel = Channel::fromContext(Channel::mcp('client-1', 'Claude')->toContext());

    expect($channel->kind)->toBe(ChannelKind::Mcp)
        ->and($channel->clientId)->toBe('client-1')
        ->and($channel->clientName)->toBe('Claude')
        ->and($channel->withClientName('Renamed')->clientName)->toBe('Renamed')
        ->and($channel->clientName)->toBe('Claude');
});

it('falls back to the web channel for nothing or something unknown', function () {
    expect(Channel::fromContext([])->kind)->toBe(ChannelKind::Web)
        ->and(Channel::fromContext(['kind' => 'smoke-signal'])->kind)->toBe(ChannelKind::Web);
});
