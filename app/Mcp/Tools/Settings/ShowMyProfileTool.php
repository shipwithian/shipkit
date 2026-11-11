<?php

namespace App\Mcp\Tools\Settings;

use App\Mcp\Support\ProfileFields;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show_my_profile')]
#[Title('Show my profile')]
#[Description('Show the signed-in user\'s profile: name, email, and whether the email is verified.')]
#[IsReadOnly]
#[IsIdempotent]
class ShowMyProfileTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize('view', $user);

        return Response::structured(ProfileFields::from($user));
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return ProfileFields::schema($schema);
    }
}
