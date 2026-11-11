<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Auth\CreateApiTokenAction;
use App\Actions\Auth\RevokeApiTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StorePersonalAccessTokenRequest;
use App\Models\User;
use App\Queries\Auth\ApiTokenQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PersonalAccessTokenController extends Controller
{
    /**
     * Create a personal access token. Its secret is flashed once, for the page to show a single time.
     */
    public function store(StorePersonalAccessTokenRequest $request, CreateApiTokenAction $createApiToken): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $user);

        $token = $createApiToken->handle($user, $request->string('name')->toString());

        Inertia::flash('personalAccessToken', [
            'name' => $token->getToken()?->name,
            'secret' => $token->accessToken,
        ]);

        return back();
    }

    /**
     * Revoke one of the user's personal access tokens.
     */
    public function destroy(
        Request $request,
        string $token,
        ApiTokenQuery $apiToken,
        RevokeApiTokenAction $revokeApiToken,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $user);

        $revokeApiToken->handle($apiToken->handle($user, $token));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }
}
