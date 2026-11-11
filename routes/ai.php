<?php

use App\Enums\ApiScope;
use App\Http\Middleware\LimitRegisteredClientsToMcp;
use App\Http\Middleware\RecordApiTokenUse;
use App\Http\Middleware\ShareChannel;
use App\Mcp\Servers\AppServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;
use ShipKit\Core\ChannelKind;

/*
| AI clients find the app's sign-in through discovery, register themselves (public, rate-limited,
| and limited to `mcp:use`), and connect through the consent screen. Tools act as the signed-in
| user, through the same policies, Actions, and Queries as the web app.
*/

Route::middleware(['throttle:mcp-register', LimitRegisteredClientsToMcp::class])->group(function (): void {
    Mcp::oauthRoutes();
});

Mcp::web('/mcp', AppServer::class)->middleware([
    'auth:api',
    CheckToken::using(ApiScope::McpUse->value),
    'throttle:mcp',
    RecordApiTokenUse::class,
    ShareChannel::class.':'.ChannelKind::Mcp->value,
]);
