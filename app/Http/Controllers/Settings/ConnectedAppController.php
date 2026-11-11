<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Auth\RevokeConnectedAppAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Auth\ConnectedAppQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ConnectedAppController extends Controller
{
    /**
     * Disconnect an app the user approved: its access and refresh tokens stop working.
     */
    public function destroy(
        Request $request,
        string $client,
        ConnectedAppQuery $connectedApp,
        RevokeConnectedAppAction $revokeConnectedApp,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $user);

        $revokeConnectedApp->handle($user, $connectedApp->handle($user, $client));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('App disconnected.')]);

        return back();
    }
}
