<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * The profile fields an MCP tool may expose, listed one by one, and their schema.
 */
final class ProfileFields
{
    /**
     * @return array{id: int, name: string, email: string, email_verified: bool}
     */
    public static function from(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
        ];
    }

    /**
     * @param  bool  $required  false when a result may carry only some of the fields
     * @return array<string, mixed>
     */
    public static function schema(JsonSchema $schema, bool $required = true): array
    {
        $fields = [
            'id' => $schema->integer()->description('The user id.'),
            'name' => $schema->string()->description('The user\'s name.'),
            'email' => $schema->string()->description('The user\'s email address.'),
            'email_verified' => $schema->boolean()->description('Whether the email address is verified.'),
        ];

        return $required ? array_map(fn ($field) => $field->required(), $fields) : $fields;
    }
}
