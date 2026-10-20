<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Actions\UpdatePasswordAction;
use App\Domain\Settings\Queries\PasskeyListQuery;
use App\Http\Controllers\Controller;
use App\Http\Props\Settings\PasskeyListItem;
use App\Http\Props\Settings\SecurityEditProps;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request, PasskeyListQuery $passkeyList): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $user);

        $canManageTwoFactor = Features::canManageTwoFactorAuthentication();
        $canManagePasskeys = Features::canManagePasskeys();

        if ($canManageTwoFactor) {
            $request->ensureStateIsValid();
        }

        return Inertia::render('settings/security', new SecurityEditProps(
            canManageTwoFactor: $canManageTwoFactor,
            twoFactorEnabled: $canManageTwoFactor && $user->hasEnabledTwoFactorAuthentication(),
            requiresConfirmation: $canManageTwoFactor && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            canManagePasskeys: $canManagePasskeys,
            passkeys: $canManagePasskeys
                ? array_values(array_map(PasskeyListItem::from(...), $passkeyList->handle($user)->all()))
                : [],
            passwordRules: Password::defaults()->toPasswordRulesString(),
        ));
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request, UpdatePasswordAction $updatePassword): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $user);

        $updatePassword->handle($user, $request->string('password')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
