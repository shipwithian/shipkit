<?php

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\AccessControl\ListRolesTool;
use App\Models\User;
use ShipKit\Core\AccessControl\SystemPermission;

it('tells the AI client clearly when the user may not view roles', function () {
    AppServer::actingAs(User::factory()->create())->tool(ListRolesTool::class)
        ->assertHasErrors(['This action is unauthorized.']);
});

it('lists the roles with exactly the listed fields', function () {
    $user = userWithPermissions([SystemPermission::ManageRolesResource->value]);

    $response = AppServer::actingAs($user)->tool(ListRolesTool::class)->assertOk();

    $response->assertExactStructuredContentStructure(['roles' => ['*' => ['id', 'name', 'is_protected', 'permissions_count', 'users_count']]]);
});
