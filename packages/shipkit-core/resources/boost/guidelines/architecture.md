# Laravel with Actions and Queries

ShipKit is a standard Laravel application: the folders of Laravel's React starter kit, with a few disciplines on top that `shipkit-core` enforces with architecture tests. Writes go through Actions, reads go through Query objects, models guard their own invariants, and controllers coordinate HTTP.

```text
Write: Form Request → Controller → Action → Eloquent model → Database
Read:  Controller → Query object → Eloquent model → Database
```

A **feature** is an area of the app with its own vocabulary and rules, such as `AccessControl`, `Auth`, or `Settings`. It is a folder name, used everywhere the feature appears: `app/Actions/{Feature}`, `app/Queries/{Feature}`, `app/Http/Controllers/{Feature}`, `app/Http/Requests/{Feature}`, `app/Http/Props/{Feature}`, and `tests/Feature/{Feature}`. Copy the shape of real classes rather than inventing one: `CreateRoleAction`, `RoleListQuery`, `Role`, and `RoleController` in `AccessControl`.

## Structure

```text
app/
├── Actions/
│   ├── Fortify/           Fortify's own actions, as Laravel ships them
│   └── {Feature}/         Create{Model}Action, Update{Model}Action, Delete{Model}Action
├── Queries/{Feature}/     {Model}ListQuery
├── Models/                every Eloquent model, User included
├── Policies/              {Model}Policy, discovered by Laravel's naming convention
├── Exceptions/            {Model}CannotBeUpdated, one class per broken rule
├── Notifications/, Concerns/, Providers/
└── Http/
    ├── Controllers/       {Feature}/{Model}Controller, Api/V1/{Model}Controller
    ├── Requests/          {Feature}/Store{Model}Request, Api/V1/...
    ├── Props/             SharedProps, {Feature}/{Page}Props, {Feature}/{Model}ListItem
    └── Resources/         Api/V1/{Model}Resource

resources/js/pages/{feature}/    pages and partials, see vendor/shipwithian/shipkit-core/resources/rules/js.md
resources/js/types/generated.ts  payload types generated from app/Http/Props
tests/Feature/{Feature}/         feature tests, see vendor/shipwithian/shipkit-core/resources/rules/tests.md
```

Every class lives in Laravel's folder for its kind, and `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php` fails on an Action outside `app/Actions/{Feature}`, a Query outside `app/Queries/{Feature}`, or a model outside `app/Models`. Features are discovered from the folders of `app/Actions` and `app/Queries`. Create one with `php artisan make:feature {Name}`, which also scaffolds its rule file and index row. Each feature's rule file, `.ai/rules/features/{feature}.md`, lists its vocabulary, its invariants and where each is enforced, and its Actions and Queries; update it when the feature gains a term, an invariant, or a class.

`app/Actions/Fortify` and `app/Concerns` keep Fortify's and the starter kit's conventions, because Fortify names those classes and their methods. The shared code is the `shipwithian/shipkit-core` package (`ShipKit\Core`, developed in `packages/shipkit-core`): `DomainException` and the guard traits. Projects update it with Composer; it never depends on the application, and reads the guarded models from `config/shipkit.php`.

## Actions and invariants

An Action is one named write, `{Verb}{Model}Action`, with a single `handle()` method. It takes the typed values it needs, and when it needs to know who is acting, the actor is an explicit first parameter named `$actor` that the controller passes as `$request->user()`; Actions, Queries, models, and policies never read `auth()` or `request()`. It never accepts a Form Request or returns an HTTP response. It owns the transaction for a multi-step write, and dispatches events and other side effects only after the write succeeds. Details: `vendor/shipwithian/shipkit-core/resources/rules/actions.md`.

Use an Action for a named workflow: it creates a record, changes state, coordinates several models or systems, needs a transaction, or deserves its own test. Keep small model-local changes on the model.

Business invariants hold for every caller, independently of authorization. A policy decides whether this actor may attempt the operation; the invariant decides whether the operation is valid at all.

- A rule about one model's own state is an `ensure...()` method on the model, called from its `guardCreate()`, `guardUpdate()` and `guardDelete()` overrides, with `use GuardsInvariants;`. The trait runs those guards on every create, save, and delete, even with model events silenced, and refuses query-builder bulk writes.
- A rule about a relationship ("a protected role keeps its protected permissions") sits on a pivot model that uses `GuardsPivotInvariants`, attached with `->using()` on both sides of the relation.
- A rule that spans several models or systems stays in the Action.
- Every invariant throws a named exception in `app/Exceptions` extending `DomainException`; `bootstrap/app.php` renders it as a validation error.

Cascades, row locks, and the full pattern: `vendor/shipwithian/shipkit-core/resources/rules/domain.md`.

## HTTP boundary

- Controllers coordinate HTTP: `Gate::authorize()` as the first statement, then one Action for a write or one Query object for a read, then the response. They do not query models or use `DB`. Route model binding does not replace authorization.
- Form Requests validate and normalize input only. They never authorize.
- Routes apply authentication, verification, and throttling, never permission middleware.
- API resources and Inertia props list every exposed field and never a secret. Inertia props are typed PHP classes in `app/Http/Props`, and the frontend imports the TypeScript types `composer types:generate` produces from them; payload types are generated, never hand-written.

Details: `vendor/shipwithian/shipkit-core/resources/rules/controllers.md`, `requests.md`, `routes.md`, `policies.md`, `responses.md`.

## Reads, models, services, events

- A Query object lives in `app/Queries/{Feature}/`, exposes one `handle()`, takes the trusted actor and typed filters, eager loads what the response needs, returns models or a paginator, and never writes. Every read behind a page, list, or API response goes through one; controllers never query the database themselves. Details: `vendor/shipwithian/shipkit-core/resources/rules/queries.md`.
- Eloquent models own persistence, relationships, casts, scopes, small model-local behavior, and their `ensure...()` invariants.
- Services are for logic shared across Actions, a named calculation, or an external capability. Avoid catch-all `{Model}Service` classes.
- Events represent completed facts, such as `{Model}Created`. Do not hide workflow in observers; the one exception is a model's own guards.

## Testing and checks

Feature tests are the main confidence layer, organized by feature. Test Actions and Query objects directly for invariants with several branches or non-trivial filtering. Details: `vendor/shipwithian/shipkit-core/resources/rules/tests.md`.

Before handing work back, run `composer ci:check`, which runs every check including the full suite; `composer fix` repairs formatting and Rector findings. The layout and boundary rules live in `shipkit-core` (`vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php`) and update with the package; never edit them in `vendor/`. Add a check for a project-specific rule under `tests/` rather than relying on review, and record a deliberate exception in `config/shipkit.php` with its reason.

## Agent Workflow

ShipKit's path rules ship with `shipkit-core` in `vendor/shipwithian/shipkit-core/resources/rules/`, mapped by `.ai/rules/index.md` next to the project's own feature rules; when you search the rules for a keyword, search that folder as well as `.ai/rules`. Its workflow skills ship in `vendor/shipwithian/shipkit-core/resources/boost/skills/` and the project's own in `.ai/skills/`; `php artisan boost:update` syncs both to every agent. Never edit `shipkit-core`'s guidance in `vendor/`, where `composer update` overwrites it: propose the change to ShipKit, override a guideline at `.ai/guidelines/shipwithian/shipkit-core/{name}.md`, which Boost uses in its place, or add a project rule in `.ai/rules/`. Reach for the skills by situation:

- **New area of the app with its own vocabulary:** `new-feature`, which runs `php artisan make:feature {Name}`.
- **Feature work:** `to-spec` → `to-tickets` → `implement`, tracked in GitHub Issues as described in `.ai/issue-tracker.md`. `review-diff` reviews a branch against these rules and its own checklist.
- **A bug or regression:** `diagnosing-bugs`.
- **Proving behaviour in the running app:** `verify-app`.
- **Shaping a feature's language:** `domain-modeling` or `grill-with-docs`.
- **After a session, or when the same correction repeats:** `retro` and `correct`. `.ai/rules/enforcement.md` pairs every rule with the check that enforces it; prefer adding a check over adding a rule.

### What agents may merge

Agents may merge project-owned feature code alone, on the handoff evidence and merge rule in the `implement` skill: a regression test shown failing without the change for every invariant change and bug fix, a green `composer ci:check`, and a `review-diff` verdict of "Agent may merge" from a session that did not write the code.

A change to the checks themselves needs the maintainer. The repository's CODEOWNERS file lists those paths, and branch protection requires the maintainer's review on them. Without a CODEOWNERS file, these are maintainer-owned:

- the `shipkit-core` version, and `config/shipkit.php`, where check exceptions are recorded;
- agent guidance (`.ai/`, `boost.json`, and the `CLAUDE.md` and `AGENTS.md` compiled from them);
- CI workflows, and the configuration of every check (PHPUnit, PHPStan, Rector, Pint, TypeScript, Vite, and the Composer and npm scripts);
- the guarded models of protected records, and the tests that enforce a rule in `.ai/rules/enforcement.md`.

Never weaken, skip, or work around a check to get a change merged.
