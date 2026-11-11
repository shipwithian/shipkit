<?php

namespace App\Mcp\Tools\Settings;

use App\Actions\Settings\UpdateProfileAction;
use App\Concerns\ProfileValidationRules;
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

#[Name('update_my_profile')]
#[Title('Update my profile')]
#[Description('Change the signed-in user\'s name and email. Changing the email marks it unverified until the user verifies the new address.')]
#[IsIdempotent]
class UpdateMyProfileTool extends Tool
{
    use ProfileValidationRules;
    use ReportsDomainExceptions;

    public function handle(Request $request, UpdateProfileAction $updateProfile): Response|ResponseFactory
    {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize('update', $user);

        /** @var array{name: string, email: string} $validated */
        $validated = $request->validate($this->profileRules($user->id), [
            'name.required' => 'Give the user\'s full name, for example "Ada Lovelace".',
            'email.required' => 'Give the email address to use, for example "ada@example.com".',
            'email.email' => 'The email must be a valid email address, for example "ada@example.com".',
            'email.unique' => 'Another account already uses this email address; ask the user for a different one.',
        ]);

        return $this->reportingDomainExceptions(
            fn (): ResponseFactory => Response::structured(ShowMyProfileTool::profile($updateProfile->handle($user, $validated))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The user\'s full name.')->required(),
            'email' => $schema->string()->description('The user\'s email address.')->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return ShowMyProfileTool::profileSchema($schema);
    }
}
