# ShipKit

ShipKit is an opinionated Laravel and React starter application for building new projects on top of a production-minded application foundation.

It is designed to be used as a **project template**, not installed as a Laravel package. Each project created from ShipKit becomes an independent application that can evolve around its own requirements.

> Stop rebuilding the foundation. Start building the product.

## Quick start

Create a new project with the Laravel installer:

```bash
laravel new my-app --using=shipwithian/shipkit
cd my-app
php artisan db:seed
composer dev
```

The installer copies ShipKit into `my-app`, creates `.env`, generates the application key, and runs the migrations. Seeding adds the protected access-control defaults and a local Super Admin account.

Prefer to start from GitHub? Use the repository's **Use this template** button, or follow [Create a project from ShipKit](#create-a-project-from-shipkit) to clone it and start a fresh history. That section also covers environment configuration and the checklist to run through before building features.

ShipKit ships as a single template: web authentication, roles and permissions, and a versioned Sanctum API are all included.

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

The dashboard uses web/session authentication. API clients authenticate separately with Sanctum bearer tokens.

### API authentication

The API foundation includes:

- Versioned `/api/v1` authentication endpoints
- Sanctum bearer tokens with configurable expiration
- API registration, login, logout, and current-user endpoints
- TOTP and recovery-code two-factor challenges
- API email verification and password reset flows
- Policy- and permission-based authorization
- A public `/api/up` health endpoint

The API does not replace the web dashboard authentication and does not enable stateful SPA cookie authentication.

### Roles and permissions

Authorization is powered by Spatie Laravel Permission and currently includes:

- A protected, environment-configured Super Admin account
- Protected system roles and permissions
- Role resource management
- Permission resource management
- A dedicated role-permission assignment interface
- Permission-aware navigation and controls
- Policy-based backend authorization

ShipKit does **not** currently include general user administration or user-role assignment screens. Those should be added by a project when its requirements are known.

### Application interface

The application includes an Inertia and React interface with:

- Responsive application layouts and navigation
- Light and dark appearance modes
- Typed Wayfinder routes and controller actions
- Shared authenticated-user and permission data
- Reusable form, dialog, table, badge, and feedback patterns

## Technology

| Area               | Technology                                |
| ------------------ | ----------------------------------------- |
| Backend            | Laravel 13, PHP 8.3+                      |
| Frontend           | React 19, TypeScript, Inertia.js 3        |
| Styling            | Tailwind CSS 4                            |
| Authentication     | Laravel Fortify                           |
| API authentication | Laravel Sanctum (API-enabled branch)      |
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

ShipKit uses Laravel's standard application structure, Eloquent's Active Record pattern, and focused Actions for application workflows.

The normal write flow is:

```text
Form Request
    ↓
Controller authorization
    ↓
Focused Action
    ↓
Eloquent model
    ↓
Database
```

Reads follow the same path with a Query object in place of the Form Request and Action.

Responsibilities are intentionally separated:

- **Form Requests** validate and normalize input.
- **Controllers** authorize the request, invoke an Action or Query object, and return the response. They do not query the database directly.
- **Policies** map application abilities to user permissions.
- **Actions** perform named workflows and enforce non-bypassable system invariants.
- **Query objects** own the Eloquent query behind each page, list, or API response.
- **Eloquent models** own persistence, relationships, casts, scopes, and small model-local behavior.
- **React pages** present server-provided state and improve usability without replacing backend authorization.

DTOs, repositories, services, and domain-oriented structures are not required by default. Introduce them only when they solve a concrete complexity in the project.

For the complete architecture guidance, see [`.ai/guidelines/architecture.md`](.ai/guidelines/architecture.md).

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
```

This keeps resource CRUD access independent from specific business operations.

### Protected records

System users, roles, and permissions may be marked as protected. Protection is a system invariant rather than an actor permission, so it cannot be bypassed by assigning broader permissions.

The access-control seeder:

- Creates or finds and protects the Super Admin account and role
- Creates and protects required system permissions
- Preserves additional permissions assigned to the Super Admin role
- Migrates legacy system permission names without losing assignments
- Can be run repeatedly without duplicating system records

### Frontend permission contract

Inertia shares the authenticated user and all direct or role-inherited permissions using this contract:

```ts
export type Auth = {
    user: User;
    permissions: string[];
};
```

The frontend uses `auth.permissions` to show appropriate navigation and controls. These checks improve the interface; backend policies remain the authoritative security boundary.

## Create a project from ShipKit

The Laravel installer flow in [Quick start](#quick-start) is the shortest path. The steps below create the project from the GitHub repository instead and apply to both flows from step 3 onward.

### 1. Create an independent repository

Create an empty repository for the new project on GitHub. Do not fork ShipKit or initialize the new repository with a README, license, or `.gitignore`.

Clone ShipKit:

```bash
git clone --branch main --single-branch https://github.com/shipwithian/shipkit.git YOUR_PROJECT
cd YOUR_PROJECT
```

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

Also review `SANCTUM_TOKEN_EXPIRATION` and the API authentication routes before exposing the application to API clients.

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

## New-project checklist

Before starting business features:

- [ ] Create the project with the Laravel installer, or initialize an empty repository from ShipKit.
- [ ] Update `APP_NAME`, `APP_URL`, and project branding.
- [ ] Update the root package name, description, and keywords in `composer.json`.
- [ ] Replace the placeholder landing page copy in `resources/js/pages/welcome/partials/content.ts`.
- [ ] Configure the database, mail, cache, session, queue, and filesystem for the project.
- [ ] Set unique `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, and `SUPER_ADMIN_PASSWORD` values.
- [ ] Run migrations and seed the access-control defaults.
- [ ] Sign in as the Super Admin and verify role and permission management.
- [ ] Review Sanctum expiration, API policies, and bearer-token flows, or remove the API routes if the project does not need them.
- [ ] Establish the project's independent semantic-version sequence and release tags.
- [ ] (Optional) Configure `TYPESAFE_API_KEY`, install the TypeSafe Codex skill, and verify the project-scoped Jev MCP server.
- [ ] Run the automated quality checks and production build.
- [ ] Update this README with the project's business purpose and capabilities.

## Development commands

```bash
# Start local development
composer dev

# Run PHP formatting, static analysis, and the test suite
composer test

# Run the complete CI-oriented check
composer ci:check

# Preview pending Laravel upgrade transformations
composer rector:check

# Apply configured Laravel upgrade transformations
composer rector

# Check frontend formatting and lint rules
npm run check

# Check TypeScript
npm run types:check

# Create a production build
npm run build
```

When working on a focused change, run the narrowest relevant Pest tests first, then run the complete checks before handing the work off.

Rector is configured only for composer-version-aware Laravel upgrades. Run it deliberately after PHP or Laravel dependency upgrades, review every generated diff, and then run Pint, PHPStan, and the test suite. Rector is not used as an automatic formatter or as a replacement for code review.

## Extending a generated project

Start with the application's business capabilities rather than adding more foundation layers.

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

## Bringing improvements back to ShipKit

Projects created from ShipKit do not automatically receive later template changes. They are independent repositories by design.

When a downstream project discovers a better pattern:

1. Decide whether the improvement is generic or project-specific.
2. Implement generic improvements in ShipKit for future projects.
3. Backport the change manually to existing projects when it is valuable.

This keeps generated applications stable while allowing the template to improve over time.

## Guiding principle

> Start Laravel-native. Extract complexity only when the domain requires it.

ShipKit optimizes for clarity, maintainability, testability, fast development, and low architectural overhead while leaving room for stronger domain boundaries when an application genuinely needs them.
