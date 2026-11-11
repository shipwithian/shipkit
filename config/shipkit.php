<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Guarded models
    |--------------------------------------------------------------------------
    |
    | The application's Role, Permission, and User models. ShipKit reads them from here instead of
    | assuming class names, and refuses to boot when a mapped model does not use GuardsInvariants.
    |
    */

    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
        'user' => User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Architecture exceptions
    |--------------------------------------------------------------------------
    |
    | Deliberate departures from ShipKit's architecture checks, each with the reason, so they stay
    | visible and reviewable instead of being hidden by editing a check.
    |
    */

    'architecture' => [
        'features_using_framework_exceptions' => [
            'Auth' => 'mirrors Fortify, which reports with validation and authorization exceptions',
        ],
        'non_feature_folders' => [
            'app/Http/Controllers' => ['Api' => 'versioned API controllers, grouped by version'],
            'app/Http/Requests' => ['Api' => 'versioned API requests, grouped by version'],
            'tests/Feature' => ['Mcp' => 'the MCP server as a whole: transport, rate limit, self-connect, and the confirmation pattern'],
        ],
        'protected_marker_exceptions' => [
            'tests/Feature/AccessControl/AccessControlInvariantsTest.php' => 'attempts to change the marker to prove the models refuse it',
        ],
        'long_frontend_files' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guarded cascades
    |--------------------------------------------------------------------------
    |
    | Cascading deletes on guarded tables that are deliberate, keyed by "table.column", with the
    | reason. A cascade deletes rows without running their guards, so it is only safe when the
    | parent owns those rows as parts of itself (a team and its memberships).
    |
    */

    'guarded_cascades' => [],

];
