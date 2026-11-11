<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\CompleteApiTwoFactorChallengeAction;
use App\Actions\Auth\LoginApiUserAction;
use App\Actions\Auth\RegisterApiUserAction;
use App\Actions\Auth\RevokeApiTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\TwoFactorChallengeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\OAuthToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Fortify;
use Laravel\Passport\AccessToken;
use Laravel\Passport\PersonalAccessTokenResult;

class AuthenticationController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterApiUserAction $registerApiUser,
    ): JsonResponse {
        /** @var array{name: string, email: string, password: string, password_confirmation: string, device_name: string} $validated */
        $validated = $request->validated();

        $token = $registerApiUser->handle(
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
            ],
            $validated['device_name'],
        );

        return $this->tokenResponse($token, 201);
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

        if (! $result instanceof PersonalAccessTokenResult) {
            return response()->json([
                'two_factor_required' => true,
                ...$result,
            ]);
        }

        return $this->tokenResponse($result);
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

        return $this->tokenResponse($token);
    }

    public function logout(
        Request $request,
        RevokeApiTokenAction $revokeApiToken,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $user);

        /** @var AccessToken<mixed> $token */
        $token = $user->token();
        $revokeApiToken->handle($token);

        return response()->noContent();
    }

    /**
     * @param  PersonalAccessTokenResult<mixed>  $token
     */
    private function tokenResponse(
        PersonalAccessTokenResult $token,
        int $status = 200,
    ): JsonResponse {
        /** @var OAuthToken $issued */
        $issued = $token->getToken();
        /** @var User $user */
        $user = $issued->user;
        $user->loadMissing(['permissions', 'roles.permissions']);

        return response()->json([
            'token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $issued->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ], $status);
    }
}
