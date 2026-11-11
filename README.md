# ShipKit

ShipKit is an opinionated Laravel and React starter application for building new projects on top of a production-minded application foundation.

It is designed to be used as a **project template**, not installed as a Laravel package. Each project created from ShipKit becomes an independent application that can evolve around its own requirements.

> Stop rebuilding the foundation. Start building the product.

## Quick start

Create a new project with the Laravel installer:

```bash
laravel new my-app --using=shipwithian/shipkit --git --npm
```

This installs the latest ShipKit release. The installer lets Composer choose development versions, so ShipKit's `composer.json` declares its `main` branch as `0.x-dev`, below every release; without that, `main`, whose `shipkit-core` cannot be updated, would win. Add a version such as `--using="shipwithian/shipkit:3.0.0"` to install an exact release. `--git` starts the project as a git repository and `--npm` installs and builds the frontend; both are optional.

Move into the project:

```bash
cd my-app
```

Seed the access-control defaults:

```bash
php artisan db:seed
```

Start development:

```bash
composer dev
```

The installer copies ShipKit into `my-app`, creates `.env`, generates the application key, and runs the migrations. It also generates the agent skills for Claude Code and Codex into `.claude/skills` and `.agents/skills`. Boost detects the React skill only once `node_modules` exists, so run `php artisan boost:update` once after the first `npm install`; `composer setup` does it in that order for you. Seeding adds the protected access-control defaults and a local Super Admin account.

Without the Laravel installer, `composer create-project shipwithian/shipkit my-app` does the same. To start from GitHub instead, follow [Create a project from ShipKit](#create-a-project-from-shipkit), which also covers environment configuration and the checklist to run through before building features.

Every project requires [`shipwithian/shipkit-core`](https://github.com/shipwithian/shipkit-core), the package that holds ShipKit's guards and checks, from Packagist. The rest of the code is yours to change. [Updating a project](#updating-a-project) explains what updates and how.

ShipKit ships as a single template: web authentication, roles and permissions, a versioned API, and an OAuth server are all included.

## Why ShipKit exists

Most applications need the same foundation before business development can begin: authentication, account security, authorization, layouts, navigation, testing, and architectural conventions.

ShipKit provides that foundation up front:

```text
Create from ShipKit
        ↓
Configure the project
        ↓
Start building business features
```

Laravel remains the framework. ShipKit is a maintained starting point, not a framework layered on top of Laravel.

## Included foundation

### Authentication and account security

Authentication is powered by Laravel Fortify and currently includes:

- Registration, login, and logout
- Password reset and password confirmation
- Email verification
- Profile and password management
- Two-factor authentication with recovery codes
- Passkey support
- Protected account deletion

The dashboard uses web/session authentication. API clients authenticate separately with Passport bearer tokens.

### API authentication

The API foundation includes:

- Versioned `/api/v1` authentication endpoints
- Personal access tokens with configurable expiration, created from the API or Settings → Security
- An OAuth 2.1 server, so other apps can ask a user for access through a consent screen
- API registration, login, logout, and current-user endpoints
- TOTP and recovery-code two-factor challenges
- API email verification and password reset flows
- Policy- and permission-based authorization
- A public `/api/up` health endpoint

The API does not replace the web dashboard authentication and does not enable stateful SPA cookie authentication.

#### Tokens, scopes, and connected apps

ShipKit uses [Laravel Passport](https://laravel.com/docs/passport). There are two kinds of access:

- **Personal access tokens** are for the user's own scripts and tools. API login, registration, and `POST /api/v1/tokens` return one, and Settings → Security creates, lists, and revokes them. Each expires after `PERSONAL_ACCESS_TOKEN_LIFETIME_DAYS` (90 by default) and its secret is shown once.
- **Connected apps** are other applications a user lets act for them. The app sends the user to `/oauth/authorize`; the user signs in (with two-factor authentication when enabled), sees a consent screen naming the app, the scopes it asks for, and the account it will act as, and approves or denies. The app then exchanges the code at `/oauth/token`. Access tokens last an hour and refresh tokens 30 days. Settings → Security lists connected apps and disconnects them.

Every `/api/v1` route requires the `api` scope, listed with its consent-screen description in `App\Enums\ApiScope`. Only the authorization-code (with PKCE for public clients), refresh-token, and personal-access grants are enabled.

Create a client for an app with `passport:client`. Use `--public` for an app that cannot keep a secret, such as a CLI, desktop, or mobile app; it must use PKCE:

```bash
php artisan passport:client --public
```

A password reset revokes every personal access token and every connected app's tokens, and deleting an account removes them.

#### Connect an AI client

ShipKit is an MCP server at `/mcp`. AI clients connect on their own: they find the app's sign-in through discovery, register themselves, open the consent screen, and keep refreshed tokens. Tools act as the signed-in user, through the same policies, Actions, and Queries as the web app.

In Claude Code:

```bash
claude mcp add --transport http my-app https://my-app.example.com/mcp
```

```bash
claude mcp login my-app
```

In claude.ai or ChatGPT, add `https://my-app.example.com/mcp` as a custom connector. A client without OAuth support can send a personal access token instead, as `Authorization: Bearer <token>`:

```bash
curl -X POST https://my-app.example.com/mcp \
  -H 'Authorization: Bearer <token>' \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

A client that registers itself may only redirect back to claude.ai, chatgpt.com, or localhost, so a look-alike site cannot register as a trusted client. To allow another client, add its origin to `MCP_REDIRECT_DOMAINS` (comma-separated, with scheme; a `*` is ignored, because it would let any site register as a trusted client), and for a desktop client with its own URL scheme, such as Cursor, add the scheme to `custom_schemes` in `config/mcp.php`.

The connected client then appears under Settings → Security → Connected apps, where the user can disconnect it. Its token works on `/mcp` only, never on `/api/v1`. The first tools are `show_my_profile`, `update_my_profile`, and `list_roles`; a feature adds its own in `app/Mcp/Tools/{Feature}`, following the `mcp` rule, and a tool that costs money or cannot be undone confirms with the user before acting.

#### Passport keys

Passport signs tokens with a key pair. `composer setup` and project creation run `php artisan passport:keys`, which writes `storage/oauth-private.key` and `storage/oauth-public.key` (both gitignored). In production, set `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` instead, or run `passport:keys` once on the server. Keep the same keys across deploys: new keys invalidate every issued token.

### Roles and permissions

Authorization is powered by Spatie Laravel Permission and currently includes:

- A protected, environment-configured Super Admin account
- Protected system roles and permissions
- Role resource management
- Permission resource management
- A dedicated role-permission assignment interface
- A Users page that assigns roles to users, gated by `assign roles`. Adding or removing a role requires holding every permission in it, so nobody can grant more access than they have; holders of `assign permissions` are exempt, since they can already change any role
- Navigation and controls shown from abilities the server computes with the same policies
- Policy-based backend authorization

ShipKit does **not** include general user administration, such as creating, editing, or deleting users. Add it when a project's requirements are known.

### Application interface

The application includes an Inertia and React interface with:

- Responsive application layouts and navigation
- Light and dark appearance modes
- Typed Wayfinder routes and controller actions
- Shared authenticated user and server-computed abilities
- TypeScript types for every page payload, generated from PHP
- Reusable form, dialog, table, badge, and feedback patterns

## Technology

| Area               | Technology                                |
| ------------------ | ----------------------------------------- |
| Backend            | Laravel 13, PHP 8.3+                      |
| Frontend           | React 19, TypeScript, Inertia.js 3        |
| Styling            | Tailwind CSS 4                            |
| Authentication     | Laravel Fortify                           |
| API authentication | Laravel Passport (OAuth 2.1)              |
| Authorization      | Spatie Laravel Permission                 |
| Typed routes       | Laravel Wayfinder                         |
| Frontend tooling   | Vite+                                     |
| Testing            | Pest                                      |
| Code quality       | PHPStan, Laravel Pint, Rector, TypeScript |

Exact dependency constraints are maintained in `composer.json` and `package.json`.

## Optional Jev developer quality workflow

ShipKit includes a project-scoped Codex MCP configuration for [Jev](https://docs.typesafe.ai/), TypeSafe's typed judgment layer. Jev is an advisory development tool for risk classification, focused review, and completion verification. It does not modify code, commit changes, merge pull requests, or replace Pest, Pint, PHPStan, Rector, TypeScript checks, or human review.

The configuration is tracked in [`.codex/config.toml`](.codex/config.toml), alongside the Laravel Boost server, so every project created from ShipKit gets the same MCP registration. The Jev MCP package is pinned to a known version for repeatable setup; update that pin deliberately when upgrading the workflow.

### Benefits

Jev gives every ShipKit-based project a shared, lightweight quality workflow around development:

- **Risk-based effort:** classify changes before choosing review and test depth. Documentation-only work can stay lightweight, while authentication, authorization, database, dependency, and deployment changes can receive deeper checks.
- **Focused review:** review the relevant diff and evidence instead of relying on broad, generic feedback.
- **More reliable handoffs:** check completion claims against the proposed changes and reported test evidence before calling work finished.
- **Consistent project setup:** every project generated from ShipKit can use the same advisory checkpoints without adding Jev to the Laravel runtime.
- **Safe escalation:** missing keys, service failures, malformed results, and low-confidence judgments route to normal deterministic checks and human review.

Jev improves where attention goes; it does not prove correctness. Pest, Pint, PHPStan, Rector, TypeScript checks, CI, and human review remain the authoritative quality gates.

### One-time developer setup

Each developer needs to complete these steps on their own machine:

1. Create a TypeSafe API key and store it in the user environment as `TYPESAFE_API_KEY`. Never put the key in this repository, a Laravel `.env` file, a `VITE_` variable, a prompt, or a committed Codex configuration file.

    For a temporary shell session, the shape is:

    ```bash
    export TYPESAFE_API_KEY='your-new-typesafe-key'
    ```

    For regular use, store the variable through your local shell profile or secret manager, then restart Codex so the desktop, CLI, or IDE process can read it.

2. Install the official TypeSafe agent skill for Codex:

    ```bash
    npx skills add typesafe-ai/skills --skill typesafe-ai -g
    ```

3. Open the trusted ShipKit project in Codex Desktop, CLI, or IDE and restart the client if it was already open. The project-scoped configuration supplies the Jev MCP server and forwards only the local `TYPESAFE_API_KEY` environment variable.
4. Confirm that the `jev` MCP server is listed, then use the enabled tools: `jev_classify`, `jev_review`, and `jev_gate`.

For a local smoke test, ask Codex to classify a synthetic change description. A healthy response contains typed judgment data with confidence or probability information. Test with a focused diff and only the relevant repository context; do not send `.env` files, credentials, `vendor`, build output, or unrelated source files.

Jev is used at three optional checkpoints:

- classify change risk and affected areas before selecting review and test depth;
- review a focused diff with `jev_review`;
- verify acceptance claims and test evidence with `jev_gate` before handoff.

If the key is missing, authentication fails, Jev is rate-limited or unavailable, or a result is malformed or low-confidence, route the change to normal human review and deterministic project checks. Jev must never be an approval bypass.

## Architecture

ShipKit is a standard Laravel application: the folders of Laravel's React starter kit, with Actions for writes, Query objects for reads, and models that guard their own invariants. Pest architecture tests keep it that way.

```text
app/
├── Actions/
│   ├── Fortify/          Fortify's own actions, as Laravel ships them
│   ├── AccessControl/    roles, permissions, role assignment
│   ├── Auth/             API login, two-factor challenges, tokens, verification
│   └── Settings/         profile, password, account deletion
├── Queries/              one folder per feature, like Actions
├── Models/               User, Role, Permission, and their pivots
├── Policies/
├── Exceptions/           one named exception per broken business rule
└── Http/                 controllers, Form Requests, Inertia props, API resources
```

A feature, such as `AccessControl`, uses one name everywhere: `app/Actions/AccessControl`, `app/Queries/AccessControl`, `app/Http/Controllers/AccessControl`, and `tests/Feature/AccessControl`.

The write flow is:

```text
HTTP request
    ↓
Form Request (validates)
    ↓
Controller (authorizes)
    ↓
Action
    ↓
Eloquent model (ensure...() invariants)
    ↓
Database
```

Reads follow the same path with a Query object in place of the Form Request and Action.

Responsibilities are intentionally separated:

- **Form Requests** validate and normalize input.
- **Controllers** authorize the request, invoke an Action or Query object, and return the response. They do not query the database directly.
- **Policies** map application abilities to user permissions.
- **Actions** perform named workflows and enforce business rules that span several models.
- **Query objects** own the Eloquent query behind each page, list, or API response.
- **Eloquent models** own persistence, relationships, casts, and `ensure...()` methods that protect their own state on every write.
- **Named exceptions** describe each broken business rule and render as validation errors.
- **React pages** present server-provided state and improve usability without replacing backend authorization.

There are no repositories, mappers, or separate domain entities: Fortify, Passport, Spatie Permission, and the Gate all work with Eloquent models, and so does ShipKit.

The rules are checked by `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php`, including that each class sits in its standard folder, and each feature's vocabulary and invariants are documented for agents in `.ai/rules/features/`. For the complete architecture guidance, see [`vendor/shipwithian/shipkit-core/resources/boost/guidelines/architecture.md`](vendor/shipwithian/shipkit-core/resources/boost/guidelines/architecture.md).

For the story of how this architecture was chosen, and how it was evaluated by letting agents build real features unaided, see [How ShipKit was built](docs/how-shipkit-was-built.md).

## Agent skills

ShipKit's shared skills, path rules, and architecture guideline ship with `shipkit-core` (`vendor/shipwithian/shipkit-core/resources/`), so `composer update` brings their improvements; project-specific skills live in `.ai/skills/`. `php artisan boost:update` syncs both to Claude Code and Codex alongside Laravel Boost's package skills. `composer setup` runs it, so a fresh clone gets the skills along with the committed `boost.json`, `.mcp.json`, `CLAUDE.md`, and `AGENTS.md`. After editing `.ai/guidelines`, run `php artisan boost:update` and commit the regenerated `CLAUDE.md` and `AGENTS.md`; `AgentToolingTest` fails until you do.

| Skill                                            | Use it to                                                                                      |
| ------------------------------------------------ | ---------------------------------------------------------------------------------------------- |
| `new-feature`                                    | Create a feature with `php artisan make:feature {Name}`                                        |
| `to-spec`, `to-tickets`, `implement`             | Turn a conversation into a GitHub issue spec, split it into tickets, and build them test-first |
| `review-diff`                                    | Review a branch against the `.ai` rules and its originating issue                              |
| `diagnosing-bugs`                                | Debug with a red-capable feedback loop before hypothesising                                    |
| `verify-app`                                     | Drive an isolated copy of the app over HTTP and capture evidence                               |
| `domain-modeling`, `grill-with-docs`, `grilling` | Sharpen a feature's language into `.ai/rules/features/`                                        |
| `retro`, `correct`                               | Turn repeated agent mistakes into checks                                                       |
| `writing-for-agents`, `handoff`                  | Write agent documents; hand work to a fresh session                                            |

Several skills are adapted from [mattpocock/skills](https://github.com/mattpocock/skills) and [pstack](https://github.com/cursor/plugins/tree/main/pstack), both MIT licensed. Each adapted skill keeps its upstream `LICENSE` and names its source in `metadata.source`. To update one, compare it with the upstream file, re-apply the ShipKit adaptations (`.ai/rules` paths, GitHub Issues, Pest, `composer ci:check`), and keep the license. `tests/Feature/AgentToolingTest.php` checks that every skill has valid frontmatter, its license, and only references paths, commands, and links that exist.

## Authorization conventions

ShipKit authorization is permission-based:

- There is no Super Admin Gate bypass.
- Policies use permission checks rather than role-name checks.
- Controllers explicitly authorize protected operations through policies.
- Form Requests remain validation-only.
- Routes use authentication and verification middleware, not permission middleware.
- Access-control records use the `web` guard.

The Super Admin role has no special runtime behavior. Its access comes exclusively from its seeded permissions.

### Permission names

A permission covering the standard resource-controller operations uses:

```text
manage {plural resource} resource
```

Current examples:

```text
manage roles resource
manage permissions resource
```

A workflow outside standard resource management receives its own verb permission:

```text
assign permissions
assign roles
```

This keeps resource CRUD access independent from specific business operations.

### Protected records

System users, roles, and permissions may be marked as protected. Protection is a system invariant rather than an actor permission, so it cannot be bypassed by assigning broader permissions:

- Protected roles and permissions cannot be renamed or deleted, and protected users cannot be deleted.
- A protected role keeps its protected permissions, and the last Super Admin cannot lose the role.
- The protected marker never changes after a record is created, in either direction.

Only `shipkit-core`'s access-control bootstrap, which the access-control seeder calls, creates protected records. Seeding:

- Creates the missing system permissions, the Super Admin role, and the Super Admin account, protected from the start
- Gives the Super Admin role its system permissions and the account its role, keeping any other permissions the role holds
- Stops with `SystemRecordIsNotProtected` when a record with a system name exists without protection, instead of adopting it
- Can be run repeatedly without duplicating or rewriting system records

### Frontend abilities contract

The frontend never reads permission names. Each page receives the abilities its controls need, computed on the server with `Gate::allows()` against the same policy ability the controller authorizes, and the shared `auth` prop carries the navigation abilities:

```ts
export type AuthProps = {
    readonly user: AuthUser | null;
    readonly can: NavigationAbilities;
};

export type NavigationAbilities = {
    readonly viewRoles: boolean;
    readonly viewPermissions: boolean;
    readonly viewUsers: boolean;
};
```

These types are generated from the PHP props classes by `composer types:generate`. A page shows a control only when its `can` says so, such as `can.create` or `role.can.update`; `shipkit-core`'s `FrontendAbilitiesTest` fails on a permission-name check like `permissions.includes('...')`. The abilities improve the interface; backend policies remain the authoritative security boundary.

## Create a project from ShipKit

The Laravel installer flow in [Quick start](#quick-start) is the shortest path. The steps below create the project from the GitHub repository instead and apply to both flows from step 3 onward.

### 1. Create an independent repository

Create an empty repository for the new project on GitHub. Do not fork ShipKit or initialize the new repository with a README, license, or `.gitignore`.

Clone the latest ShipKit release. Use the newest tag from the [releases page](https://github.com/shipwithian/shipkit/releases) in place of `v3.0.0`:

```bash
git clone --branch v3.0.0 --depth 1 https://github.com/shipwithian/shipkit.git YOUR_PROJECT
cd YOUR_PROJECT
```

Clone a release tag, not `main`, and do not use GitHub's **Use this template** button, which copies `main`. `main` is ShipKit's development branch: it builds `shipkit-core` from the copy in `packages/` instead of requiring the published package, so a project started from it cannot update its guards and checks with Composer.

Remove the template Git history, initialize the project's own repository, and push it to the empty remote:

Run the following while inside the newly cloned `YOUR_PROJECT` directory. It removes the template history so the project can establish its own independent version sequence.

```bash
rm -rf .git
git init -b main
git add .
git commit -m "chore: initialize project from ShipKit"
git remote add origin https://github.com/YOUR_GITHUB_OWNER/YOUR_PROJECT.git
git push -u origin main
```

The generated repository is now independent from ShipKit. Its version sequence and release tags should be established separately from the template.

### 2. Install and initialize the application

Run the project setup script:

```bash
composer setup
```

`composer setup` creates `.env` from `.env.example` when it is missing, generates the application key, runs migrations, installs PHP and frontend dependencies, and creates a production frontend build. If the project needs non-default database settings before the first migration, create and configure `.env` before running setup; otherwise let the setup script create it.

### 3. Configure the environment and seed defaults

Review the generated `.env` file:

```env
APP_NAME="Your Project"
APP_URL=http://localhost:8000

SUPER_ADMIN_NAME="Your Name"
SUPER_ADMIN_EMAIL=you@example.com
SUPER_ADMIN_PASSWORD=use-a-unique-password
```

The example Super Admin credentials are for local setup only. Replace them before seeding a real environment or deploying the application.

Also review `PERSONAL_ACCESS_TOKEN_LIFETIME_DAYS` and the API authentication routes before exposing the application to API clients.

SQLite is configured by default. Update the `DB_*` variables before setup if the project will use PostgreSQL, MySQL, or another supported database.

Seed the protected access-control defaults:

```bash
php artisan migrate --seed
```

### 4. Start development

```bash
composer dev
```

The development command starts the configured Laravel and frontend development processes.

Local mail goes to [Mailpit](https://mailpit.axllent.org) on `127.0.0.1:1025`. When the `mailpit` binary is installed (`brew install mailpit`), `composer dev` runs it as the `mail` process (registered by `shipkit-core`; set `dev.mailpit` to `false` in `config/shipkit.php` to turn it off), and verification, password reset, and other emails land in the inbox at http://localhost:8025. Without Mailpit, set `MAIL_MAILER=log` to write emails to `storage/logs/laravel.log` instead. Tests always use the `array` mailer.

## New-project checklist

Before starting business features:

- [ ] Create the project with the Laravel installer, or initialize an empty repository from a ShipKit release tag.
- [ ] Update `APP_NAME`, `APP_URL`, and project branding.
- [ ] Update the root package name, description, and keywords in `composer.json`.
- [ ] Replace the placeholder landing page copy in `resources/js/pages/welcome/partials/content.ts`.
- [ ] Configure the database, mail, cache, session, queue, and filesystem for the project.
- [ ] Set unique `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, and `SUPER_ADMIN_PASSWORD` values.
- [ ] Run migrations and seed the access-control defaults.
- [ ] Sign in as the Super Admin and verify role and permission management.
- [ ] Review token lifetimes, API policies, and bearer-token flows, or remove the API routes if the project does not need them.
- [ ] Provide stable Passport keys in production (`PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`).
- [ ] Establish the project's independent semantic-version sequence and release tags.
- [ ] (Optional) Configure `TYPESAFE_API_KEY`, install the TypeSafe Codex skill, and verify the project-scoped Jev MCP server.
- [ ] Run the automated quality checks and production build.
- [ ] Update this README with the project's business purpose and capabilities.
- [ ] (Optional) Delete or replace `docs/how-shipkit-was-built.md`; it tells ShipKit's own story, not your project's.

## Development commands

Start local development:

```bash
composer dev
```

Run PHP formatting, static analysis, and the test suite:

```bash
composer test
```

Run the complete CI-oriented check:

```bash
composer ci:check
```

Preview pending Laravel upgrade transformations:

```bash
composer rector:check
```

Apply configured Laravel upgrade transformations:

```bash
composer rector
```

Check frontend formatting and lint rules:

```bash
npm run check
```

Check TypeScript:

```bash
npm run types:check
```

Create a production build:

```bash
npm run build
```

When working on a focused change, run the narrowest relevant Pest tests first, then run the complete checks before handing the work off.

`composer fix` runs Rector, Pint, and the JS and markdown formatter in one step; review its diff, then run `composer ci:check`. Rector is configured only for composer-version-aware Laravel upgrades. Run it deliberately after PHP or Laravel dependency upgrades, review every generated diff, and then run Pint, PHPStan, and the test suite. Rector is not used as an automatic formatter or as a replacement for code review.

## Extending a generated project

Start with the application's business capabilities rather than adding more foundation layers. Give each capability with its own vocabulary and invariants a feature with `php artisan make:feature {Name}`: its Actions in `app/Actions/{Feature}`, its Queries in `app/Queries/{Feature}`, and a rule file in `.ai/rules/features/`. `shipkit-core`'s architecture checks discover every feature and cover it without changes.

For example, an ecommerce project might add products, orders, payments, and inventory. A community-management project might add residents, households, certificates, and programs. Those features belong to their individual applications, not to ShipKit.

Project-specific permissions should follow the same resource-versus-workflow distinction used by the foundation:

```text
manage products resource
manage orders resource
approve orders
cancel orders
```

## What belongs in ShipKit

A capability is a good candidate for ShipKit when it is broadly useful across applications and establishes a reusable application foundation. Examples include:

- Authentication and account security
- General authorization infrastructure
- Application layouts and navigation
- Shared architecture and testing conventions
- Common development and quality tooling

Business-specific features—such as products, orders, students, residents, appointments, or invoices—belong in the generated project.

## When to create a package

ShipKit itself remains a starter application. Consider extracting a Laravel package when a capability:

- Is reused across multiple applications
- Behaves substantially the same in each application
- Has a clear, isolated responsibility
- Should receive updates independently from the ShipKit template

Do not introduce a package solely to avoid keeping application-level code in the application.

## Updating a project

A project created from ShipKit gets two kinds of updates, in two different ways.

**`shipkit-core`: guards, checks, rules, and skills.** These come from Composer:

```bash
composer update shipwithian/shipkit-core
```

```bash
composer ci:check
```

`composer update` also runs `php artisan boost:update`, so the refreshed agent rules and skills arrive with the package. The project's constraint, such as `^3.0`, keeps it within one major version, and a minor or patch release never makes a passing project fail. A new major version tightens a guard, so moving to it is a deliberate step: require it with `composer require shipwithian/shipkit-core:^4.0`, then follow its section in the package's [`UPGRADE.md`](packages/shipkit-core/UPGRADE.md), which names each check that may now fail and the fix.

**Template code: controllers, Actions, Queries, models, pages, and configuration.** Nothing updates it. The project copied it once and owns it from then on. To adopt an improvement from a later ShipKit release, read its section in [`CHANGELOG.md`](CHANGELOG.md), compare the two release tags on GitHub, for example `https://github.com/shipwithian/shipkit/compare/v2.0.0...v3.0.0`, and copy the parts you want.

## Bringing improvements back to ShipKit

Projects created from ShipKit do not receive later template changes; only `shipkit-core` updates, as [Updating a project](#updating-a-project) describes. They are independent repositories by design.

When a downstream project discovers a better pattern:

1. Decide whether the improvement is generic or project-specific.
2. Implement generic improvements in ShipKit for future projects.
3. Backport the change manually to existing projects when it is valuable.

This keeps generated applications stable while allowing the template to improve over time.

## Guiding principle

> Start Laravel-native. Extract complexity only when the domain requires it.

ShipKit optimizes for clarity, maintainability, testability, fast development, and low architectural overhead while leaving room for stronger domain boundaries when an application genuinely needs them.
