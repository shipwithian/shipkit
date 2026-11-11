<?php

namespace App\Mcp\Tools\Settings;

use App\Mcp\Concerns\ReportsDomainExceptions;
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
    use ReportsDomainExceptions;

    public function handle(Request $request): Response|ResponseFactory
    {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize('view', $user);

        return Response::structured(self::profile($user));
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return self::profileSchema($schema);
    }

    /**
     * The profile fields a tool may expose, listed one by one.
     *
     * @return array{id: int, name: string, email: string, email_verified: bool}
     */
    public static function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profileSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The user id.')->required(),
            'name' => $schema->string()->description('The user\'s name.')->required(),
            'email' => $schema->string()->description('The user\'s email address.')->required(),
            'email_verified' => $schema->boolean()->description('Whether the email address is verified.')->required(),
        ];
    }
}
