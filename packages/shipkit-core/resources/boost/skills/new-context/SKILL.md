---
name: new-context
description: Create a new bounded context in app/Domain. Use when a feature introduces its own business vocabulary or invariants, such as billing, catalog, or teams, rather than extending an existing context.
---

# New bounded context

A context earns its own folder when it has its own vocabulary and its own invariants. A feature that only adds behaviour to roles, permissions, or the user's settings belongs in that existing context; check `app/Domain/` first.

## Steps

1. **Scaffold.** Run `php artisan make:context {Name}` with a StudlyCase name. It creates `app/Domain/{Name}/{Actions,Queries,Models,Exceptions,Policies}`, `tests/Feature/{Name}/`, the rule file `.ai/rules/contexts/{name}.md`, and its row in `.ai/rules/index.md`. `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php` discovers the context on its own. Done when `php artisan test --compact --testsuite=ShipKit` passes.
2. **Model the language.** Call the Skill tool with "domain-modeling" and fill in the rule file's Vocabulary section with the user. Done when every term the first feature needs has a one-line definition.
3. **Build the first slice.** Model, migration, and factory with `php artisan make:model 'App\Domain\{Name}\Models\{Model}' -mf --no-interaction` (the factory lands in `database/factories/Domain/{Name}/Models/`; `AppServiceProvider` maps it to the model by namespace, so no `$model` property is needed), an Action under `Actions/`, a Query under `Queries/`, a policy under `Policies/`, and a controller, Form Request, and page props classes under `app/Http/Controllers/{Name}/`, `app/Http/Requests/{Name}/`, and `app/Http/Props/{Name}/`. List each Action, Query, and exception in the rule file's Public surface.
4. **Guard each invariant.** For every rule that must hold for every caller:
    - add an `ensure...()` method on the model that throws a named exception extending `ShipKit\Core\Exceptions\DomainException`;
    - call it from the model's `guardCreate()`, `guardUpdate()` or `guardDelete()` override (`guardCreate()` for a limit such as "at most 50 per user"), and add `use GuardsInvariants;` to the model;
    - give the model's table foreign keys `restrictOnDelete()` rather than `cascadeOnDelete()`, so a database cascade can't skip the guard (`GuardedTablesTest` fails otherwise unless you record why the parent owns the rows);
    - add a row to the rule file's Invariants table;
    - add a feature test that calls the model directly and asserts the named exception;
    - for a rule about a relationship (an owner must remain, a protected record keeps its parts), put the guard on a pivot model with `GuardsPivotInvariants` and attach it with `->using()` on both sides, as `RolePermission` and `RoleAssignment` do.

    See `app/Domain/AccessControl/Models/Role.php` for the complete pattern.

5. **Verify.** Run `composer ci:check`. Done when it passes with no architecture check weakened.
