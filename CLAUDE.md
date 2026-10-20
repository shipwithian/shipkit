<laravel-boost-guidelines>
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

=== shipwithian/shipkit-core/architecture rules ===

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
│   ├── Props/             SharedProps, {Context}/{Page}Props, {Context}/{Model}ListItem
│   └── Resources/         Api/V1/{Model}Resource
└── Models/User.php

resources/js/pages/{feature}/    pages and partials, see vendor/shipwithian/shipkit-core/resources/rules/js.md
resources/js/types/generated.ts  payload types generated from app/Http/Props
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
- API resources and Inertia props list every exposed field and never a secret. Inertia props are typed PHP classes in `app/Http/Props`, and the frontend imports the TypeScript types `composer types:generate` produces from them; payload types are generated, never hand-written.

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

</laravel-boost-guidelines>
