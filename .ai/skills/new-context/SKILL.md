---
name: new-context
description: Create a new bounded context in app/Domain. Use when a feature introduces its own business vocabulary or invariants, such as billing, catalog, or teams, rather than extending an existing context.
---

# New bounded context

A context earns its own folder when it has its own vocabulary and its own invariants. A feature that only adds behaviour to roles, permissions, or the user's settings belongs in that existing context; check `app/Domain/` first.

## Steps

1. **Scaffold.** Run `php artisan make:context {Name}` with a StudlyCase name. It creates `app/Domain/{Name}/{Actions,Queries,Models,Exceptions,Policies}`, `tests/Feature/{Name}/`, the rule file `.ai/rules/contexts/{name}.md`, and its row in `.ai/rules/index.md`. `tests/Unit/ArchitectureTest.php` discovers the context on its own. Done when `php artisan test --compact tests/Unit` passes.
2. **Model the language.** Call the Skill tool with "domain-modeling" and fill in the rule file's Vocabulary section with the user. Done when every term the first feature needs has a one-line definition.
3. **Build the first slice.** Model, migration, and factory with `php artisan make:model 'App\Domain\{Name}\Models\{Model}' -mf --no-interaction` (the factory lands in `database/factories/Domain/{Name}/Models/` and resolves automatically), an Action under `Actions/`, a Query under `Queries/`, a policy under `Policies/`, and a controller and Form Request under `app/Http/Controllers/{Name}/` and `app/Http/Requests/{Name}/`. List each Action, Query, and exception in the rule file's Public surface.
4. **Guard each invariant.** For every rule that must hold for every caller:
    - add an `ensure...()` method on the model that throws a named exception extending `App\Domain\Shared\Exceptions\DomainException`;
    - register it in the model's `booting()` as an `updating` or `deleting` guard;
    - add `use GuardsBulkWrites;` to the model;
    - add a row to the rule file's Invariants table;
    - add a feature test that calls the model directly and asserts the named exception.

    See `app/Domain/AccessControl/Models/Role.php` for the complete pattern.

5. **Verify.** Run `composer ci:check`. Done when it passes with no architecture check weakened.
