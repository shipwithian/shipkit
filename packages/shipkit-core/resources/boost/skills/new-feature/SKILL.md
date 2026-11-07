---
name: new-feature
description: Create a new feature with its own Actions, Queries, and rule file. Use when work introduces its own business vocabulary or invariants, such as billing, catalog, or teams, rather than extending an existing feature.
---

# New feature

A feature earns its own folders when it has its own vocabulary and its own invariants. Work that only adds behaviour to roles, permissions, authentication, or the user's settings belongs in that existing feature; check `app/Actions/` and `app/Queries/` first.

## Steps

1. **Scaffold.** Run `php artisan make:feature {Name}` with a StudlyCase name. It creates `app/Actions/{Name}/`, `app/Queries/{Name}/`, `tests/Feature/{Name}/`, the rule file `.ai/rules/features/{name}.md`, and its row in `.ai/rules/index.md`. `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php` discovers the feature on its own. Done when `php artisan test --compact --testsuite=ShipKit` passes.
2. **Model the language.** Call the Skill tool with "domain-modeling" and fill in the rule file's Vocabulary section with the user. Done when every term the first slice needs has a one-line definition.
3. **Build the first slice.** Model, migration, and factory with `php artisan make:model {Model} -mf --no-interaction`, an Action in `app/Actions/{Name}/`, a Query in `app/Queries/{Name}/`, a policy in `app/Policies/`, and a controller, Form Request, and page props classes under `app/Http/Controllers/{Name}/`, `app/Http/Requests/{Name}/`, and `app/Http/Props/{Name}/`. List each Action, Query, and exception in the rule file's Actions and Queries section.
4. **Guard each invariant.** For every rule that must hold for every caller:
    - add an `ensure...()` method on the model that throws a named exception in `app/Exceptions` extending `ShipKit\Core\Exceptions\DomainException`;
    - call it from the model's `guardCreate()`, `guardUpdate()` or `guardDelete()` override (`guardCreate()` for a limit such as "at most 50 per user"), and add `use GuardsInvariants;` to the model;
    - give the model's table foreign keys `restrictOnDelete()` rather than `cascadeOnDelete()`, so a database cascade can't skip the guard (`GuardedTablesTest` fails otherwise unless you record why the parent owns the rows);
    - add a row to the rule file's Invariants table;
    - add a feature test that calls the model directly and asserts the named exception;
    - for a rule about a relationship (an owner must remain, a protected record keeps its parts), put the guard on a pivot model with `GuardsPivotInvariants` and attach it with `->using()` on both sides, as `RolePermission` and `RoleAssignment` do.

    See `app/Models/Role.php` for the complete pattern.

5. **Verify.** Run `composer ci:check`. Done when it passes with no architecture check weakened.
