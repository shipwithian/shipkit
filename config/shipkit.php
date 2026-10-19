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

];
