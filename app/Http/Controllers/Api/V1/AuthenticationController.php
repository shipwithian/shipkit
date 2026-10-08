<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\CompleteApiTwoFactorChallengeAction;
use App\Actions\Api\LoginApiUserAction;
use App\Actions\Api\RegisterApiUserAction;
use App\Actions\ApiTokens\RevokeApiTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\TwoFactorChallengeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\NewAccessToken;

class AuthenticationController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterApiUserAction $registerApiUser,
    ): JsonResponse {
        /** @var array{name: string, email: string, password: string, password_confirmation: string, device_name: string} $validated */
        $validated = $request->validated();

        $token = $registerApiUser->handle(
            Arr::except($validated, 'device_name'),
            $validated['device_name'],
        );

        /** @var User $user */
        $user = $token->accessToken->tokenable;

        return $this->tokenResponse($token, $user, 201);
    }

    public function login(
        LoginRequest $request,
        LoginApiUserAction $loginApiUser,
    ): JsonResponse {
        $result = $loginApiUser->handle(
            $request->string(Fortify::username())->toString(),
            $request->string('password')->toString(),
            $request->string('device_name')->toString(),
        );

        if (! $result instanceof NewAccessToken) {
            return response()->json([
                'two_factor_required' => true,
                ...$result,
            ]);
        }

        /** @var User $user */
        $user = $result->accessToken->tokenable;

        return $this->tokenResponse($result, $user);
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
