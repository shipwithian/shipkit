# Laravel DDD-lite Architecture

Organize business code by bounded context, keep Eloquent as the aggregate, and enforce the boundaries with architecture tests. Each context lives in `app/Domain/{Context}` and exposes a small public surface: its Actions (writes), Query objects (reads), domain exceptions, and events. Eloquent models are the aggregate roots: they own persistence, relationships, casts, and the invariants that protect their own state. Controllers coordinate HTTP.

This is deliberately DDD-lite. Use bounded contexts, ubiquitous language, aggregate invariants, and named domain exceptions. Do not introduce separate domain entities, repositories, record mappers, or ports: Fortify, Sanctum, Spatie Permission, and the Gate all depend on Eloquent models, so a second representation of each record only adds code to keep in sync.

```text
Write: Form Request → Controller → {Context} Action → Eloquent aggregate → Database
Read:  Controller → {Context} Query object → Eloquent aggregate → Database
```

`{Context}` is a bounded context name such as `AccessControl`, and `{Model}` is a singular Eloquent model name. Copy the shape of real classes rather than inventing one: `CreateRoleAction`, `RoleListQuery`, `Role`, and `RoleController` in `AccessControl`.

Use an Action for a named workflow: it creates a record, changes state, coordinates several models or systems, needs a transaction, or deserves its own test. Keep small model-local changes on the model. Use a Query object for every read behind a page, list, or API response; controllers never query the database themselves.

## Bounded Contexts

A bounded context groups everything that shares one business vocabulary and one set of invariants. ShipKit ships three:

- `AccessControl`: roles, permissions, and their protected system records.
- `Settings`: the authenticated user's own account: profile, password, passkeys, and account deletion. It shares its name with the settings routes, controllers, requests, and pages.
- `Authentication`: API login, two-factor challenges, registration, password reset, email verification, and API tokens.

- A context's Models, Enums, Policies, and Services are internal. Its public surface is its Actions, Query objects, domain exceptions, and events, which other contexts may call, catch, and declare. The delivery layer (`app/Http`) may use any context.
- `App\Models\User` is the shared identity model. It stays in `app/Models` because Fortify, Sanctum, Spatie Permission, and the auth configuration reference it.
- Each context has a rule file, `.ai/rules/contexts/{context}.md`, listing its vocabulary, its invariants and where each is enforced, and its public surface. Update it when a context gains a term, an invariant, or a public class.
- `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php` enforces these boundaries and discovers contexts from `app/Domain/*`. Create a context with `php artisan make:context {Name}`, which also scaffolds its rule file and index row. Do that when a capability has its own vocabulary and invariants, such as `Billing`; not for a single class.
- The shared kernel is the `shipwithian/shipkit-core` package (`ShipKit\Core`, developed in `packages/shipkit-core`), not a context: `DomainException` and the guard traits. Projects update it with Composer; it never depends on the application, and reads the guarded models from `config/shipkit.php`.

## Structure

```text
packages/shipkit-core/src/
├── Eloquent/              GuardedBuilder, GuardsInvariants, GuardsPivotInvariants
└── Exceptions/            DomainException, BulkWriteBypassesInvariants

app/
├── Domain/
│   └── {Context}/
│       ├── Actions/       Create{Model}Action, Update{Model}Action, Delete{Model}Action
│       ├── Queries/       {Model}ListQuery
│       ├── Models/        {Model}
│       ├── Policies/      {Model}Policy
│       ├── Exceptions/    {Model}CannotBeUpdated
│       ├── Enums/, Events/, Services/    only when they hold a class
├── Http/
│   ├── Controllers/       {Context}/{Model}Controller, Api/V1/{Model}Controller
│   ├── Requests/          {Context}/Store{Model}Request, Api/V1/...
│   └── Resources/         Api/V1/{Model}Resource
└── Models/User.php

resources/js/pages/{feature}/    pages and partials, see vendor/shipwithian/shipkit-core/resources/rules/js.md
tests/Feature/{Context}/         feature tests, see vendor/shipwithian/shipkit-core/resources/rules/tests.md
```

A context has one name everywhere (`app/Domain/Settings`, `app/Http/Controllers/Settings`, `tests/Feature/Settings`), and `ArchitectureTest` fails on a folder that matches no context. Policies live in their context and are discovered automatically; a policy for a model outside `app/Domain`, such as `User`, is attached with `#[UsePolicy]`. Classes whose names and contracts a package dictates, such as the Fortify actions in `app/Actions/Fortify`, keep the package's conventions.

## Actions and invariants

An Action is one named use case, `{Verb}{Model}Action`, with a single `handle()` method. It takes the typed values it needs, and when it needs to know who is acting, the actor is an explicit first parameter named `$actor` that the controller passes as `$request->user()`; domain code never reads `auth()` or `request()`. It never accepts a Form Request or returns an HTTP response. It owns the transaction for a multi-step write, and dispatches events and other side effects only after the write succeeds. Details: `vendor/shipwithian/shipkit-core/resources/rules/actions.md`.

Business invariants hold for every caller, independently of actor authorization. A policy decides whether this actor may attempt the operation; the invariant decides whether the operation is valid at all.

- A rule about one aggregate's own state is an `ensure...()` method on the model, called from its `guardCreate()`, `guardUpdate()` and `guardDelete()` overrides, with `use GuardsInvariants;`. The trait runs those guards on every create, save, and delete, even with model events silenced, and refuses query-builder bulk writes.
- A rule about a relationship ("a protected role keeps its protected permissions") sits on a pivot model that uses `GuardsPivotInvariants`, attached with `->using()` on both sides of the relation.
- A rule that spans several aggregates or systems stays in the Action.
- Every invariant throws a named exception extending `DomainException`; `bootstrap/app.php` renders it as a validation error.

Cascades, row locks, and the full pattern: `vendor/shipwithian/shipkit-core/resources/rules/domain.md`.

## HTTP boundary

- Controllers coordinate HTTP: `Gate::authorize()` as the first statement, then one Action for a write or one Query object for a read, then the response. They do not query models or use `DB`. Route model binding does not replace authorization.
- Form Requests validate and normalize input only. They never authorize.
- Routes apply authentication, verification, and throttling, never permission middleware.
- API resources and Inertia props list every exposed field and never a secret.

Details: `vendor/shipwithian/shipkit-core/resources/rules/controllers.md`, `requests.md`, `routes.md`, `policies.md`, `responses.md`.

## Reads, models, services, events

- A Query object lives in `app/Domain/{Context}/Queries/`, exposes one `handle()`, takes the trusted actor and typed filters, eager loads what the response needs, returns models or a paginator, and never writes. Details: `vendor/shipwithian/shipkit-core/resources/rules/queries.md`.
- Eloquent models own persistence, relationships, casts, scopes, small model-local behavior, and their `ensure...()` invariants.
- Services are for logic shared across Actions, a named calculation, or an external capability. Avoid catch-all `{Model}Service` classes.
- Events represent completed facts, such as `{Model}Created`. Do not hide workflow in observers; the one exception is an aggregate's own guards.

## Testing and checks

Feature tests are the main confidence layer, organized by context. Test Actions and Query objects directly for invariants with several branches or non-trivial filtering. Details: `vendor/shipwithian/shipkit-core/resources/rules/tests.md`.

Before handing work back, run `composer ci:check`, which runs every check including the full suite; `composer fix` repairs formatting and Rector findings. The boundary rules live in `shipkit-core` (`vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php`) and update with the package; never edit them in `vendor/`. Add a check for a project-specific rule under `tests/` rather than relying on review, and record a deliberate exception in `config/shipkit.php` with its reason.

## Agent Workflow

ShipKit's path rules ship with `shipkit-core` in `vendor/shipwithian/shipkit-core/resources/rules/`, mapped by `.ai/rules/index.md` next to the project's own context rules; when you search the rules for a keyword, search that folder as well as `.ai/rules`. Its workflow skills ship in `vendor/shipwithian/shipkit-core/resources/boost/skills/` and the project's own in `.ai/skills/`; `php artisan boost:update` syncs both to every agent. Never edit `shipkit-core`'s guidance in `vendor/`, where `composer update` overwrites it: propose the change to ShipKit, override a guideline at `.ai/guidelines/shipwithian/shipkit-core/{name}.md`, which Boost uses in its place, or add a project rule in `.ai/rules/`. Reach for the skills by situation:

- **New capability with its own vocabulary:** `new-context`, which runs `php artisan make:context {Name}`.
- **Feature work:** `to-spec` → `to-tickets` → `implement`, tracked in GitHub Issues as described in `.ai/issue-tracker.md`. `review-diff` reviews a branch against these rules and its own checklist.
- **A bug or regression:** `diagnosing-bugs`.
- **Proving behaviour in the running app:** `verify-app`.
- **Shaping the domain language:** `domain-modeling` or `grill-with-docs`.
- **After a session, or when the same correction repeats:** `retro` and `correct`. `.ai/rules/enforcement.md` pairs every rule with the check that enforces it; prefer adding a check over adding a rule.
