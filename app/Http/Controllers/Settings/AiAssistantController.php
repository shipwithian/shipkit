<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Props\Settings\AiAssistantsEditProps;
use App\Http\Props\Settings\ConnectedAppListItem;
use App\Models\User;
use App\Queries\Auth\ConnectedAppListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController extends Controller
{
    /**
     * Show how to connect an AI assistant, and the apps the user has connected.
     */
    public function edit(Request $request, ConnectedAppListQuery $connectedAppList): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $user);

        return Inertia::render('settings/ai-assistants', new AiAssistantsEditProps(
            serverName: (string) config('app.name'),
            mcpUrl: url('/mcp'),
            publicMcpUrl: url('/mcp/public'),
            connectedApps: array_values(array_map(ConnectedAppListItem::from(...), $connectedAppList->handle($user)->all())),
        ));
    }
}
