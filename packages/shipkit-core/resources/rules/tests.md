---
paths:
    - 'tests/**'
---

# Tests

## Group tests by feature

Place feature tests in `tests/Feature/{Context}/`, using the same context name as `app/Domain/{Context}`. Do not create by-type folders such as `Policies/` or `Actions/`; policy, Action, and Query tests live in their feature folder as `{Model}PolicyTest`, `{Verb}{Model}ActionTest`, and `{Model}ListQueryTest`.

## Use one file per resource with a describe block per action

Name the file for the resource or page, such as `{Model}ManagementTest`. When a file covers more than one controller action, wrap cases in a `describe()` block per action (`index`, `store`, `edit`, `update`, `destroy`). Write cases with `it()`. Test one behaviour per case; do not chain create, update, and delete in a single test.

## Keep single-purpose files flat

Files that cover one action or one class, such as policy, Action, Query, seeder, and health tests, do not need `describe()` blocks. Leave the upstream starter-kit files under `tests/Feature/Auth` as shipped.

## Split by flow when a file covers several endpoints

When a file would cover unrelated endpoints, create one file per flow, such as `Authentication/LoginTest`, `Authentication/RegistrationTest`, and `Authentication/PasswordResetTest`.

## Cover the delivery boundary in order

Within each action, order cases as guest, forbidden, validation, success, then invariants. A success case asserts the response, the database state, and applicable side effects.

## Keep shared helpers in Pest.php

Helpers used by more than one file belong in `tests/Pest.php`. Do not leave placeholder helpers or expectations.

## Leave the architecture checks to shipkit-core

Boundary rules live as Pest `arch()` expectations in `shipkit-core` (`vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php`), run through the `ShipKit` testsuite. They discover contexts from `app/Domain/*`, so a context created with `php artisan make:context` is covered without editing them. Never edit them in `vendor/`: record a deliberate exception in `config/shipkit.php` with its reason, and put a check for a project-specific rule under `tests/`. Assert invariant failures by their named domain exception, not `ValidationException`, when calling an Action directly.

## Run the full suite yourself

The whole suite runs in seconds. Run the narrowest tests while iterating, then `composer ci:check`, which runs the full suite with every other check, before handing work back; there is no need to ask the user to run it.

## Fix formatting with composer fix

When `composer ci:check` stops on formatting or Rector, run `composer fix` (Rector, Pint, and the JS and markdown formatter), review the diff, and run `composer ci:check` again. `ci:check` stops at its first failure, so expect to run it twice after a large change.

## Reset the guard after revoking a bearer token

A test that reuses a Sanctum bearer token after revoking it calls `Auth::forgetGuards()` first, so the guard authenticates again instead of returning its cached user.

## Tests do not need built assets

`tests/TestCase.php` calls `withoutVite()`, so tests pass without `npm run build`. Run the build only to check the app in a browser.

## Cover isolation and prefer real behavior

Feature tests cover authentication, authorization, validation, database state, relationships, response shape, important events, and cross-actor isolation: one actor cannot read or change another's records. Test Actions and Query objects that rely on Eloquent against the test database; do not introduce fake repositories merely to label a test a unit test. Keep fast unit tests for pure calculations and other framework-independent logic.
