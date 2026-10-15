<laravel-boost-guidelines>
=== .ai/architecture rules ===

# Laravel DDD-lite Architecture

Organize business code by bounded context, keep Eloquent as the aggregate, and enforce the boundaries with architecture tests. Each context lives in `app/Domain/{Context}` and exposes a small public surface: its Actions (writes), Query objects (reads), and events. Eloquent models are the aggregate roots: they own persistence, relationships, casts, and the invariants that protect their own state. Controllers coordinate HTTP.

This is deliberately DDD-lite. Use bounded contexts, ubiquitous language, aggregate invariants, and named domain exceptions. Do not introduce separate domain entities, repositories, record mappers, or ports: Fortify, Sanctum, Spatie Permission, and the Gate all depend on Eloquent models, so a second representation of each record only adds code to keep in sync.

```text
Write: Form Request → Controller → {Context} Action → Eloquent aggregate → Database
Read:  Controller → {Context} Query object → Eloquent aggregate → Database
```

## Template Placeholders

The examples in this guide are templates, not application classes:

- `{Context}` is a bounded context name, such as `AccessControl` or `Settings`.
- `{Model}` is a singular Eloquent model name.
- `{Action}` is a verb that describes one operation.
- `{Actor}` is the authenticated model or another trusted caller that initiates the operation.
- `{model}` is an instance of `{Model}`.
- `{Relation}` is the Eloquent relationship through which the actor owns or creates records.
- `{Field}` is a validated input field required by the operation.

For example, `{Action}{Model}Action` describes a named use-case class. Replace every placeholder with names from the context being built.

Use an Action when an operation is a named workflow: it creates a record, changes state, coordinates multiple models or systems, requires a transaction, or has behavior worth testing independently. For small model-local changes, keep the behavior on the Eloquent model.

Use a Query object for every read that builds an Eloquent query for a page, list, or API response. Controllers never query the database themselves.

## Bounded Contexts

A bounded context groups everything that shares one business vocabulary and one set of invariants. ShipKit ships three:

- `AccessControl`: roles, permissions, and their protected system records.
- `Settings`: the authenticated user's own account: profile, password, passkeys, and account deletion. It shares its name with the settings routes, controllers, requests, and pages.
- `Authentication`: API login, two-factor challenges, registration, password reset, email verification, and API tokens.

Rules for contexts:

- A context's Models, Enums, Policies, and Services are internal. Its public surface is its Actions, Query objects, domain exceptions, and events, which other contexts may call, catch, and declare.
- The delivery layer (`app/Http`) may use any context.
- `App\Models\User` is the shared identity model. It stays in `app/Models` because Fortify, Sanctum, Spatie Permission, and the auth configuration reference it.
- Each context has a rule file in `.ai/rules/contexts/{context}.md` that lists its vocabulary, its invariants and where each is enforced, and its public surface. Update it when a context gains a term, an invariant, or a public class.
- `tests/Unit/ArchitectureTest.php` enforces these boundaries and discovers contexts from `app/Domain/*`. Create a context with `php artisan make:context {Name}`, which also scaffolds its rule file and index row.

Create a new context when a capability has its own vocabulary and invariants, such as `Billing` or `Catalog`. Do not create a context for a single class.

`app/Domain/Shared` is the shared kernel, not a context: the base `DomainException` and the Eloquent support for guarded aggregates. It must not depend on any context or on `app/Http`. Add to it only infrastructure that every context needs.

## Structure

```text
app/
├── Domain/
│   ├── Shared/
│   │   ├── Eloquent/
│   │   │   ├── GuardedBuilder.php
│   │   │   └── GuardsInvariants.php
│   │   └── Exceptions/
│   │       ├── BulkWriteBypassesInvariants.php
│   │       └── DomainException.php
│   └── {Context}/
│       ├── Actions/
│       │   ├── Create{Model}Action.php
│       │   ├── Update{Model}Action.php
│       │   └── Delete{Model}Action.php
│       ├── Enums/
│       │   └── {Model}Status.php
│       ├── Events/
│       │   └── {Model}Created.php
│       ├── Exceptions/
│       │   └── {Model}CannotBeUpdated.php
│       ├── Models/
│       │   └── {Model}.php
│       ├── Policies/
│       │   └── {Model}Policy.php
│       ├── Queries/
│       │   └── {Model}ListQuery.php
│       └── Services/
│           └── {Model}CalculationService.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/{Model}Controller.php
│   │   └── {Context}/{Model}Controller.php
│   ├── Requests/
│   │   ├── Api/V1/Store{Model}Request.php
│   │   └── {Context}/
│   │       ├── Store{Model}Request.php
│   │       └── Update{Model}Request.php
│   └── Resources/
│       └── Api/V1/{Model}Resource.php
└── Models/
    └── User.php

resources/js/
├── pages/
│   └── {feature}/
│       ├── index.tsx
│       └── partials/
│           ├── {models}-table.tsx
│           ├── create-{model}-dialog.tsx
│           ├── edit-{model}-dialog.tsx
│           └── delete-{model}-dialog.tsx
└── types/
   └── {feature}.ts

tests/
├── Feature/
│   └── {Context}/
│       ├── {Model}ManagementTest.php
│       ├── {Model}PolicyTest.php
│       └── {Verb}{Model}ActionTest.php
└── Unit/
    └── ArchitectureTest.php
```

Actions and Query objects are the standard homes for writes and reads. Create the other folders in a context only when they hold a class with a clear responsibility.

The delivery layer stays outside the domain: controllers, Form Requests, resources, and middleware live in `app/Http`. Web controllers and Form Requests go in a folder named exactly after their context, `app/Http/Controllers/{Context}/` and `app/Http/Requests/{Context}/`; versioned JSON API classes go under `Api/V{n}/`. Feature tests go in `tests/Feature/{Context}/`. One name per context everywhere means an agent can find every file for a context by its name, and `ArchitectureTest` fails on any folder that does not match a context. Policies live in their context and are discovered automatically; a policy for a model outside `app/Domain`, such as `User`, is attached with `#[UsePolicy]`.

## Actions

An Action represents one named use case. It is the application-workflow layer of its context, sitting over the Eloquent aggregates; it is not a second model layer.

Use names that state intent:

- `Create{Model}Action`
- `Update{Model}Action`
- `Delete{Model}Action`
- `Publish{Model}Action`

Avoid generic manager services such as `{Model}Service` with unrelated `create`, `update`, `delete`, and `publish` methods. Those operations should normally be separate Actions.

Classes whose names and contracts are dictated by a package, such as the Fortify actions under `app/Actions/Fortify`, keep the package's conventions and location.

An Action accepts the specific typed values its operation needs. Actions may query Eloquent, create or mutate models, invoke model behavior, coordinate several models, use transactions, call focused services, dispatch events, and call external-system abstractions. They may return an Eloquent model, collection, scalar, or result object.

The following is pseudocode: replace placeholders and types with concrete PHP names before using it.

```text
class Create{Model}Action
{
   handle({Actor} actor, mixed value): {Model}
   {
       return actor.{Relation}()->create({ {Field}: value });
   }
}
```

Using Laravel and Eloquent inside an Action is intentional. The Action orchestrates; the Eloquent model persists and owns its local state. Keep a controller from becoming an unstructured application layer, but do not add an Action solely for the sake of layering.

### Invariants

Business invariants hold for every caller, independently of actor authorization: protected records, required assignments, in-use deletion guards, ownership, and similar rules. A policy decides whether this actor may attempt the operation; the invariant decides whether the operation is valid at all. Console commands, jobs, and other non-HTTP callers must not be able to bypass an invariant by skipping the controller.

Put an invariant that protects one aggregate's own state on the model as an `ensure...()` method, such as `{model}.ensureCanBeDeleted()`. Add `use GuardsInvariants;` to the model and call the `ensure...()` methods from its `guardUpdate()` and `guardDelete()` overrides. The trait runs those guards on every save and delete, so no Action, job, seeder, or tinker session can skip them: as model events registered before package events such as Spatie's pivot cleanup, directly from the save path when events are silenced (`saveQuietly()`, `deleteQuietly()`, `withoutEvents()`, `WithoutModelEvents` seeders), and by refusing query-builder bulk updates, deletes, and upserts with `BulkWriteBypassesInvariants`. To change many guarded records, load them and save or delete each one. Database cascades skip model code entirely: a foreign key into a guarded table uses `restrictOnDelete()`, and the Action that deletes the parent handles the guarded rows first. Keep invariants that span several aggregates or systems in the Action. Every invariant throws a named exception that extends `App\Domain\Shared\Exceptions\DomainException`.

### Transactions, errors, and side effects

- An Action owns the transaction boundary for a multi-step write that must commit or roll back as one operation.
- Dispatch events and other side effects only after the primary state change succeeds. Use queued work when a side effect needs retries or durability.
- Report business failures through named domain exceptions such as `{Model}CannotBeUpdated`. Each one extends `DomainException` and names the `field()` it is reported against; `bootstrap/app.php` renders it as a validation error, so the HTTP layer needs no try/catch.
- Actions never accept a Form Request and never return an HTTP response.

## Controllers and Form Requests

Controllers coordinate HTTP. A controller should normally:

1. Call `Gate::authorize()` first when the action is protected.
2. Receive the request and obtain the authenticated actor.
3. Pass only the validated, typed values the Action or Query object needs.
4. Invoke one Action for a write, or one Query object for a read.
5. Return the HTTP, Inertia, or resource response.

Controllers do not call `{Model}::query()`, build relationship queries, or use `DB`. Route model binding, and `loadMissing()` on an already-resolved model to shape a response, are allowed.

The following is pseudocode:

```text
{Model}Controller.store(
   Store{Model}Request request,
   Create{Model}Action create{Model},
): RedirectResponse
{
   Gate::authorize('create', {Model}::class);

   actor = request.authenticatedActor();

   create{Model}.handle(actor, request.validated('{Field}'));

   return redirect to {Feature}.index;
}
```

Form Requests own HTTP validation and normalization only. They do not authorize the actor; do not add `authorize()` logic to them. Do not pass a Form Request into an Action; pass only the validated values it needs, with trusted Eloquent models supplied separately. Request input must not decide trusted ownership fields, such as a foreign key, when the authenticated actor determines ownership.

## Authorization

Authorization happens at the HTTP delivery boundary:

- Routes point to controller actions and apply authentication, email verification, throttling, and signed-URL middleware. They do not apply permission middleware or define closures that contain logic.
- Every protected controller action calls `Gate::authorize()` explicitly as its first statement.
- Policies answer whether the actor may perform the operation.
- Form Requests validate only.
- Actions still enforce invariants, and Query objects still scope records to the trusted actor or tenant, so no caller can bypass them.

Route model binding constrains lookup; it does not replace authorization. A valid request is not necessarily authorized, and an authenticated actor is not automatically allowed to access every record.

## Eloquent Models: the Aggregates

Eloquent models are the aggregate roots and the persistence layer. They own table configuration, relationships, casts, scopes, small model-local behavior, and the `ensure...()` invariants that protect their own state. They may create, update, and delete their own records through normal Eloquent APIs.

The following is pseudocode:

```text
class {Model} extends Model
{
   casts: { status: {Model}Status }

   applyStateChange(): void
   {
       update({ status: {Model}Status.Completed });
   }
}
```

Prefer meaningful model behavior such as `{model}.applyStateChange()` over repeated raw state mutation. Keep workflows that coordinate multiple models or systems in an Action or focused Service. Do not move a simple model-local change into an Action just to create another class.

## Services

Services are supporting capabilities, not the center of the architecture. Use one when logic is shared across Actions, represents a named calculation, wraps an external capability, or would otherwise obscure an Action.

Use focused names such as `{Model}CalculationService`, `{Model}IntegrationService`, or `{Model}PricingService`. Avoid catch-all services that duplicate a feature's Actions.

## Queries

A Query object owns one read: the Eloquent query behind a page, list, or API response. It lives in `app/Domain/{Context}/Queries/`, is named for what it returns, such as `{Model}ListQuery`, and exposes a single `handle()` method.

A Query object:

- accepts the trusted actor, parent model, and typed filters it needs, never a Form Request;
- applies ownership, tenant, filtering, and ordering constraints;
- eager loads the relationships and counts the response needs;
- returns models, a collection, or a paginator, not arrays shaped for the client;
- does not write to the database.

The following is pseudocode:

```text
class {Model}ListQuery
{
   handle({Actor} actor): Collection<{Model}>
   {
       return actor.{Relation}().latest().get();
   }
}
```

An Action may run the queries its own write needs, such as looking up a record it is about to change. Reuse a Query object from an Action only when both need the same read.

## Response Shaping

- **JSON API endpoints** return an API Resource under `Http/Resources/Api/V{n}`, or a purpose-built `JsonResponse` when no model is returned.
- **Inertia pages** may receive models mapped to explicit arrays in the controller. List every exposed field; never pass a raw model or `toArray()` as a prop.

Resources and controller mapping transform already-loaded data. They do not query the database, trigger lazy loading, or perform application workflows. Do not expose passwords, tokens, secrets, or internal state.

## Policies, Events, and Exceptions

- **Policies** answer authorization questions at the delivery boundary. Scope sensitive record lookups to the authenticated actor or tenant before mutation or disclosure.
- **Events** represent meaningful completed facts, such as `{Model}Created` or `{Model}Updated`. An Action performs the primary operation; listeners may handle secondary reactions.
- **Exceptions** communicate broken business rules, such as `{Model}CannotBeUpdated`, and extend `DomainException`. Input-format validation stays in Form Requests; do not create custom exceptions for it.

Do not hide essential workflow in Eloquent observers. Keep critical behavior explicit in the Action. The one exception is an aggregate's own invariant guards, which `GuardsInvariants` runs so they cannot be bypassed.

## Frontend Pages

Inertia pages mirror the controller that renders them:

- `pages/{feature}/{action}.tsx` is a routable page named after the controller action (`index`, `create`, `edit`, `show`). A page that serves more than one action reads props and composes partials; a single-form page may hold its own form.
- `pages/{feature}/partials/` holds the feature's pieces, named for the action they submit to: `create-{model}-dialog.tsx` (store), `edit-{model}-dialog.tsx` (update), `delete-{model}-dialog.tsx` (destroy), and `{models}-table.tsx` (index list).
- Partials use named exports and are never rendered by name from a controller. A component used by one page or feature lives in its `partials/` and moves to `components/` only when a second feature uses it.
- Types for server-provided props live in `resources/js/types/{feature}.ts`.

Frontend permission checks only control what is shown; policies remain authoritative.

## Request Flows

### Write operations

```text
HTTP Request
   ↓
Form Request validation
   ↓
Controller authorization
   ↓
Action
   ├── Eloquent aggregate
   ├── Service
   ├── Transaction
   └── Event
   ↓
Database
   ↓
Response
```

### Read operations

```text
HTTP Request
   ↓
Controller authorization
   ↓
Query object
   ↓
Eloquent query
   ↓
Model, collection, or paginator
   ↓
Resource or Inertia props
```

## Dependency Guidelines

```text
HTTP
   ↓
{Context} Actions / Queries        ← a context's public surface
   ↓
{Context} Models / Services / Laravel
```

| Component  | May depend on                                                                                                        |
| ---------- | -------------------------------------------------------------------------------------------------------------------- |
| Controller | Requests, policies, Actions, Queries, resources, Laravel HTTP                                                        |
| Action     | Its context's models, Queries, services, events, exceptions; other contexts' Actions and Queries; Laravel facilities |
| Query      | Eloquent models, relationships, scopes, and the query builder                                                        |
| Service    | Models, external SDK abstractions, Laravel facilities when appropriate                                               |
| Model      | Eloquent, casts, relations, enums, small model-local behavior                                                        |
| Resource   | Models and Laravel resource APIs                                                                                     |
| Policy     | Models and the authenticated actor                                                                                   |

The key restrictions: controllers do not become the application layer, the domain never depends on `app/Http`, and contexts reach each other only through Actions and Query objects. Actions own workflows; Query objects own reads; Eloquent models are the aggregates.

## Testing

Use Laravel feature tests as the primary confidence layer. Organize them by context in `tests/Feature/{Context}/`, with no by-type folders: policy, Action, and Query tests sit beside the feature's HTTP tests.

Use one file per resource, such as `{Model}ManagementTest`, with one behaviour per `it()` case and a `describe()` block per controller action when the file covers more than one. Single-purpose files, such as policy, Action, and Query tests, stay flat. When a file would cover several unrelated endpoints, split it into one file per flow. Within each action, order cases as guest, forbidden, validation, success, then invariants.

Feature tests should cover authentication, authorization, validation, database state, relationships, response shape, important events, and cross-actor or tenant isolation.

Test Actions and Query objects directly when it improves confidence, for example an invariant with several branches or a query with non-trivial filtering. Actions and Query objects that rely on Eloquent may use Laravel's database test support; do not introduce fake repositories merely to label the test a unit test. Keep fast unit tests for pure calculations and similar framework-independent logic. `tests/Unit/ArchitectureTest.php` holds the Pest `arch()` rules that enforce the context boundaries; extend it rather than relying on review to catch a boundary violation.

## Agent Workflow

Project skills live in `.ai/skills/` and are synced to every agent by `php artisan boost:update`. Reach for them by situation:

- **New capability with its own vocabulary:** `new-context`, which runs `php artisan make:context {Name}`.
- **Feature work:** `to-spec` → `to-tickets` → `implement`, tracked in GitHub Issues as described in `.ai/issue-tracker.md`. `review-diff` reviews a branch against these rules.
- **A bug or regression:** `diagnosing-bugs`.
- **Proving behaviour in the running app:** `verify-app`.
- **Shaping the domain language:** `domain-modeling` or `grill-with-docs`.
- **After a session, or when the same correction repeats:** `retro` and `correct`. `.ai/rules/enforcement.md` pairs every rule with the check that enforces it; prefer adding a check over adding a rule.

## Review Checklist

- [ ] New code lives in the bounded context whose vocabulary it uses, under `app/Domain/{Context}`.
- [ ] Contexts use each other only through Actions and Query objects; `ArchitectureTest` passes.
- [ ] Each Action describes one clear use case.
- [ ] Controllers coordinate HTTP rather than own application workflows.
- [ ] Every protected controller action calls `Gate::authorize()` first.
- [ ] Controllers contain no Eloquent queries; each read goes through a Query object.
- [ ] Routes carry no permission middleware.
- [ ] Form Requests validate and normalize input only, with no authorization logic.
- [ ] Actions receive only the explicit typed values they need.
- [ ] Invariants hold independently of actor authorization: aggregate rules as `ensure...()` model methods called from `guardUpdate()`/`guardDelete()` with `GuardsInvariants`, cross-aggregate rules in Actions, each throwing a named `DomainException`.
- [ ] The context's `.ai/rules/contexts/{context}.md` lists any new term, invariant, or public class.
- [ ] Multi-step writes run in one transaction, and side effects fire after it succeeds.
- [ ] Eloquent models remain the aggregates, owning persistence configuration, small local behavior, and their own invariants.
- [ ] Services have a focused, reusable responsibility.
- [ ] API Resources and Inertia props expose only intentional, explicitly listed data.
- [ ] Ownership and authorization boundaries are enforced.
- [ ] Feature tests prove observable behavior and persisted state, grouped by context with a `describe()` block per action.
- [ ] Pages compose partials named for their controller action; shared prop types live in `types/{feature}.ts`.
- [ ] Supporting classes exist because they clarify a real responsibility.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
