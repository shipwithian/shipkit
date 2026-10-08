---
paths:
    - 'tests/**'
---

# Tests

## Group tests by feature

Place feature tests in `tests/Feature/{Feature}/`, using the same feature name as `app/Actions/{Feature}`. Do not create by-type folders such as `Policies/` or `Actions/`; policy, Action, and Query tests live in their feature folder as `{Model}PolicyTest`, `{Verb}{Model}ActionTest`, and `{Model}ListQueryTest`.

## Use one file per resource with a describe block per action

Name the file for the resource or page, such as `{Model}ManagementTest`. When a file covers more than one controller action, wrap cases in a `describe()` block per action (`index`, `store`, `edit`, `update`, `destroy`). Write cases with `it()`. Test one behaviour per case; do not chain create, update, and delete in a single test.

## Keep single-purpose files flat

Files that cover one action or one class, such as policy, Action, Query, seeder, and health tests, do not need `describe()` blocks. Leave the upstream starter-kit files under `tests/Feature/Auth` as shipped.

## Split by flow when a file covers several endpoints

When a file would cover unrelated endpoints, create one file per flow, such as `Api/LoginTest`, `Api/RegistrationTest`, and `Api/PasswordResetTest`.

## Cover the delivery boundary in order

Within each action, order cases as guest, forbidden, validation, success, then invariants. A success case asserts the response, the database state, and applicable side effects.

## Keep shared helpers in Pest.php

Helpers used by more than one file belong in `tests/Pest.php`. Do not leave placeholder helpers or expectations.
