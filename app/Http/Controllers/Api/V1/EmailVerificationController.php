<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\VerifyApiEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Notifications\ApiVerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EmailVerificationController extends Controller
{
    public function send(Request $request): JsonResponse|Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $user);

        if ($user->hasVerifiedEmail()) {
            return response()->noContent();
        }

        $user->notify(new ApiVerifyEmailNotification);

        return response()->json([
            'message' => __('A verification link has been sent to your email address.'),
        ], 202);
    }

    public function verify(int $id, string $hash, VerifyApiEmailAction $verifyApiEmail): JsonResponse
    {
        $user = $verifyApiEmail->handle($id, $hash);
        $user->loadMissing(['permissions', 'roles.permissions']);

        return response()->json([
            'message' => __('Your email address has been verified.'),
            'user' => new UserResource($user),
        ]);
    }
}
