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
    | features_using_framework_exceptions: features whose Actions and Queries report failures with
    | Laravel's own exceptions instead of named exceptions, keyed by feature.
    |
    | non_feature_folders: folders under app/Http/Controllers, app/Http/Requests, app/Http/Props, and tests/Feature
    | that are not named after a feature, keyed by parent directory, then folder.
    |
    | protected_marker_exceptions: files allowed to write is_protected, keyed by path, such as a test
    | that proves the marker cannot change. Application code never belongs here.
    |
    | long_frontend_files: files under resources/js allowed past FrontendFileLengthTest's limit
    | (200 lines for a page, 400 for any other file), keyed by path, with the reason.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Development processes
    |--------------------------------------------------------------------------
    |
    | mailpit: run Mailpit as the `mail` process of `composer dev` in a local environment where
    | the `mailpit` binary is installed. Point MAIL_PORT at 1025 and read mail at
    | http://localhost:8025. Set it to false to leave Mailpit out.
    |
    */

    'dev' => [
        'mailpit' => true,
    ],

    'architecture' => [
        'features_using_framework_exceptions' => [],
        'non_feature_folders' => [],
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
