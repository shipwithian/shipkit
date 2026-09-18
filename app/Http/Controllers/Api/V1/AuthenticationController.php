<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\AuthenticateApiUserAction;
use App\Actions\Api\CompleteApiTwoFactorChallengeAction;
use App\Actions\Api\CreateApiTwoFactorChallengeAction;
use App\Actions\ApiTokens\CreateApiTokenAction;
use App\Actions\ApiTokens\RevokeApiTokenAction;
use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\TwoFactorChallengeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Notifications\ApiVerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\NewAccessToken;

class AuthenticationController extends Controller
{
    public function register(
        RegisterRequest $request,
        CreateNewUser $createNewUser,
        CreateApiTokenAction $createApiToken,
    ): JsonResponse {
        /** @var array{name: string, email: string, password: string, password_confirmation: string, device_name: string} $validated */
        $validated = $request->validated();

        $user = $createNewUser->create($validated);
        $user->notify(new ApiVerifyEmailNotification);

        return $this->tokenResponse(
            $createApiToken->handle($user, $validated['device_name']),
            $user,
            201,
        );
    }

    public function login(
        LoginRequest $request,
        AuthenticateApiUserAction $authenticateApiUser,
        CreateApiTwoFactorChallengeAction $createApiTwoFactorChallenge,
        CreateApiTokenAction $createApiToken,
    ): JsonResponse {
        $user = $authenticateApiUser->handle($request);
        $deviceName = $request->string('device_name')->toString();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'two_factor_required' => true,
                ...$createApiTwoFactorChallenge->handle($user, $deviceName),
            ]);
        }

        return $this->tokenResponse(
            $createApiToken->handle($user, $deviceName),
            $user,
        );
    }

    public function twoFactorChallenge(
        TwoFactorChallengeRequest $request,
        CompleteApiTwoFactorChallengeAction $completeApiTwoFactorChallenge,
    ): JsonResponse {
        /** @var array{challenge_token: string, code?: string|null, recovery_code?: string|null} $validated */
        $validated = $request->validated();

        $token = $completeApiTwoFactorChallenge->handle(
            $validated['challenge_token'],
            $validated['code'] ?? null,
            $validated['recovery_code'] ?? null,
        );

        /** @var User $user */
        $user = $token->accessToken->tokenable;

        return $this->tokenResponse($token, $user);
    }

    public function logout(
        Request $request,
        RevokeApiTokenAction $revokeApiToken,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $user);

        $token = $user->currentAccessToken();
        $revokeApiToken->handle($token);

        return response()->noContent();
    }

    private function tokenResponse(
        NewAccessToken $token,
        User $user,
        int $status = 200,
    ): JsonResponse {
        $user->loadMissing(['permissions', 'roles.permissions']);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ], $status);
    }
}
