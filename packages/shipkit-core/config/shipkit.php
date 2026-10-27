<?php

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
        'role' => null,
        'permission' => null,
        'user' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Architecture exceptions
    |--------------------------------------------------------------------------
    |
    | Deliberate departures from ShipKit's architecture checks, each with the reason, so they stay
    | visible and reviewable instead of being hidden by editing a check.
    |
    | contexts_using_framework_exceptions: contexts that report failures with Laravel's own
    | exceptions instead of named domain exceptions, keyed by context.
    |
    | non_context_folders: folders under app/Http/Controllers, app/Http/Requests, app/Http/Props, and tests/Feature
    | that are not named after a bounded context, keyed by parent directory, then folder.
    |
    | protected_marker_exceptions: files allowed to write is_protected, keyed by path, such as a test
    | that proves the marker cannot change. Application code never belongs here.
    |
    | long_frontend_files: files under resources/js allowed past FrontendFileLengthTest's limit
    | (200 lines for a page, 400 for any other file), keyed by path, with the reason.
    |
    */

    'architecture' => [
        'contexts_using_framework_exceptions' => [],
        'non_context_folders' => [],
        'protected_marker_exceptions' => [],
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
