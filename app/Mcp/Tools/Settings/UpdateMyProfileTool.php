<?php

namespace App\Mcp\Tools\Settings;

use App\Actions\Settings\UpdateProfileAction;
use App\Concerns\ProfileValidationRules;
use App\Mcp\Concerns\ReportsDomainExceptions;
use App\Mcp\Support\ConsequentialActionConfirmation;
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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use ShipKit\Core\Channel;

#[Name('update_my_profile')]
#[Title('Update my profile')]
#[Description('Change the signed-in user\'s name and email. A name change applies at once. Changing the email asks for confirmation first, because it marks the email unverified until the user verifies the new address: show the summary to the user, then call again with the confirmation_token.')]
#[IsDestructive]
#[IsIdempotent]
class UpdateMyProfileTool extends Tool
{
    use ProfileValidationRules;
    use ReportsDomainExceptions;

    public function handle(Request $request, UpdateProfileAction $updateProfile, ConsequentialActionConfirmation $confirmation, Channel $channel): Response|ResponseFactory
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

        return $this->reportingDomainExceptions(function () use ($request, $user, $validated, $updateProfile, $confirmation, $channel): Response|ResponseFactory {
            $confirming = is_string($request->get('confirmation_token')) && $request->get('confirmation_token') !== '';

            if ($validated['email'] === $user->email && ! $confirming) {
                return Response::structured(ProfileFields::from($updateProfile->handle($user, $validated, $channel)));
            }

            return $confirmation->confirmBeforeActing(
                $request,
                self::class,
                $validated,
                fn (): string => self::summary($user, $validated),
                fn (): array => ProfileFields::from($updateProfile->handle($user, $validated, $channel)),
            );
        });
    }

    /**
     * What the change will do, with every field it changes, for the user to approve.
     *
     * @param  array{name: string, email: string}  $validated
     */
    private static function summary(User $user, array $validated): string
    {
        $summary = "Change your email from {$user->email} to {$validated['email']}. It will be unverified until you confirm the new address.";

        if ($validated['name'] !== $user->name) {
            $summary .= " Also change your name from {$user->name} to {$validated['name']}.";
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The user\'s full name.')->required(),
            'email' => $schema->string()->description('The user\'s email address.')->required(),
            'confirmation_token' => $schema->string()->description('The confirmation_token from the summary of an email change, sent only after the user agrees to it.'),
        ];
    }

    /**
     * The updated profile, or, for an email change that is not confirmed yet, the summary to show
     * the user and the token that confirms it.
     *
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            ...ProfileFields::schema($schema, required: false),
            'confirmed' => $schema->boolean()->description('False while an email change waits for confirmation; true once it was applied.'),
            'summary' => $schema->string()->description('What the change will do, to show the user.'),
            'confirmation_token' => $schema->string()->description('Send this back, with the same name and email, after the user agrees.'),
            'expires_in_minutes' => $schema->integer()->description('How long the confirmation_token works.'),
        ];
    }
}
