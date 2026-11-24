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
    | audience_words: the project's own role names, such as barista, that AudienceNamingTest also
    | refuses in a route's path or name and in a page folder, besides admin, staff, manager, and the like.
    |
    | audience_exceptions: route paths or page folders that are a resource rather than an audience,
    | keyed by path, with the reason, such as 'staff' in an HR app whose staff members are the records.
    |
    | raw_element_exceptions: frontend files allowed to hand-build a button, input, label, select,
    | textarea, table, or dialog instead of using its shadcn/ui primitive, keyed by path, with the
    | reason, such as a printable receipt styled for paper.
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

    /*
    |--------------------------------------------------------------------------
    | Workflow
    |--------------------------------------------------------------------------
    |
    | integration_branch: the branch every pull request targets, read by the `branches` rule, the
    | skills that open and merge pull requests, and `shipkit:doctor`. A project created from ShipKit
    | 5.3 or later sets it to `staging`, in its own config/shipkit.php from SHIPKIT_INTEGRATION_BRANCH:
    | work collects there, and one pull request into `main` is a release. This default stays `main`,
    | so a project that never set the key keeps working on one branch.
    |
    */

    'workflow' => [
        'integration_branch' => 'main',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sub-agents
    |--------------------------------------------------------------------------
    |
    | Overrides for the model and effort of ShipKit's Claude Code agents (shipkit-standards-reviewer,
    | shipkit-spec-reviewer, shipkit-explorer), keyed by agent name, which `shipkit:agents` applies
    | when it installs them into .claude/agents. For example:
    | 'shipkit-standards-reviewer' => ['model' => 'sonnet', 'effort' => 'medium'].
    |
    */

    'agents' => [],

    /*
    |--------------------------------------------------------------------------
    | Review
    |--------------------------------------------------------------------------
    |
    | `php artisan shipkit:review` runs review-diff on the current pull request in a fresh
    | read-only Claude Code session on this machine, on the user's own Claude plan, and publishes
    | the report and a shipkit/review commit status. The session coordinates; the two reviewers it
    | spawns keep the model and effort their agent definitions pin.
    |
    */

    'review' => [
        'model' => 'sonnet',
        'effort' => 'low',
        'timeout' => 1800,
    ],

    'architecture' => [
        'features_using_framework_exceptions' => [],
        'non_feature_folders' => [],
        'protected_marker_exceptions' => [],
        'long_frontend_files' => [],
        'audience_words' => [],
        'audience_exceptions' => [],
        'raw_element_exceptions' => [],
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
