<?php

use ShipKit\Core\Channel;

it('describes where a change came from', function () {
    expect(Channel::web()->label())->toBe('Web')
        ->and(Channel::api()->label())->toBe('API')
        ->and(Channel::mcp('client-1', 'Claude')->label())->toBe('MCP (Claude)')
        ->and(Channel::mcp('client-1', 'Claude')->toContext())->toBe(['kind' => 'mcp', 'client_id' => 'client-1', 'client_name' => 'Claude']);
});
