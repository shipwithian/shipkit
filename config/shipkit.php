<?php

use App\Domain\AccessControl\Models\Permission;
use App\Domain\AccessControl\Models\Role;
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
        'contexts_using_framework_exceptions' => [
            'Authentication' => 'mirrors Fortify, which reports with validation and authorization exceptions',
        ],
        'non_context_folders' => [
            'app/Http/Controllers' => ['Api' => 'versioned API controllers, grouped by version'],
            'app/Http/Requests' => ['Api' => 'versioned API requests, grouped by version'],
            'tests/Feature' => ['Auth' => "the upstream starter kit's authentication tests, kept as shipped"],
        ],
        'protected_marker_exceptions' => [
            'tests/Feature/AccessControl/AccessControlInvariantsTest.php' => 'attempts to change the marker to prove the models refuse it',
        ],
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
