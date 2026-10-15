<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Authentication\Actions\CreateApiTokenAction;
use App\Domain\Authentication\Actions\RevokeApiTokenAction;
use App\Domain\Authentication\Queries\ApiTokenListQuery;
use App\Domain\Authentication\Queries\ApiTokenQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreApiTokenRequest;
use App\Http\Resources\Api\V1\ApiTokenResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ApiTokenController extends Controller
{
    public function index(
        Request $request,
        ApiTokenListQuery $apiTokenList,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $user);

        return ApiTokenResource::collection($apiTokenList->handle($user));
    }

    public function store(
        StoreApiTokenRequest $request,
        CreateApiTokenAction $createApiToken,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $user);

        $token = $createApiToken->handle($user, $request->string('name')->toString());

        return (new ApiTokenResource($token->accessToken))
            ->additional(['token' => $token->plainTextToken, 'token_type' => 'Bearer'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(
        Request $request,
        int $token,
        ApiTokenQuery $apiToken,
        RevokeApiTokenAction $revokeApiToken,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $user);

        $revokeApiToken->handle($apiToken->handle($user, $token));

        return response()->noContent();
    }
}
