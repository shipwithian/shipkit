<?php

namespace App\Mcp\Tools\AccessControl;

use App\Models\Role;
use App\Queries\AccessControl\RoleListQuery;
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

#[Name('list_roles')]
#[Title('List roles')]
#[Description('List the roles, with how many permissions and users each has. Requires permission to view roles.')]
#[IsReadOnly]
#[IsIdempotent]
class ListRolesTool extends Tool
{
    public function handle(Request $request, RoleListQuery $roleList): Response|ResponseFactory
    {
        Gate::forUser($request->user())->authorize('viewAny', Role::class);

        return Response::structured([
            'roles' => array_values(array_map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'is_protected' => $role->is_protected,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
            ], $roleList->handle()->all())),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'roles' => $schema->array()->items($schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
                'is_protected' => $schema->boolean()->description('A system role that cannot be renamed or deleted.')->required(),
                'permissions_count' => $schema->integer()->required(),
                'users_count' => $schema->integer()->required(),
            ]))->required(),
        ];
    }
}
