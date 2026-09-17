# ShipKit

ShipKit is an opinionated Laravel and React starter application for building new projects on top of a production-minded application foundation.

It is designed to be used as a **GitHub template repository**, not installed as a Laravel package. Each project created from ShipKit becomes an independent application that can evolve around its own requirements.

> Stop rebuilding the foundation. Start building the product.

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

| Area             | Technology                                |
| ---------------- | ----------------------------------------- |
| Backend          | Laravel 13, PHP 8.3+                      |
| Frontend         | React 19, TypeScript, Inertia.js 3        |
| Styling          | Tailwind CSS 4                            |
| Authentication   | Laravel Fortify                           |
| Authorization    | Spatie Laravel Permission                 |
| Typed routes     | Laravel Wayfinder                         |
| Frontend tooling | Vite+                                     |
| Testing          | Pest                                      |
| Code quality     | PHPStan, Laravel Pint, Rector, TypeScript |

Exact dependency constraints are maintained in `composer.json` and `package.json`.

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

Responsibilities are intentionally separated:

- **Form Requests** validate and normalize input.
- **Controllers** authorize the request, invoke an Action, and return the response.
- **Policies** map application abilities to user permissions.
- **Actions** perform named workflows and enforce non-bypassable system invariants.
- **Eloquent models** own persistence, relationships, casts, scopes, and small model-local behavior.
- **React pages** present server-provided state and improve usability without replacing backend authorization.

DTOs, repositories, services, query objects, and domain-oriented structures are not required by default. Introduce them only when they solve a concrete complexity in the project.

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

### 1. Create an independent repository

On GitHub, choose **Use this template**, then create the repository for the new project. Do not fork ShipKit for normal project development.

Clone the new repository:

```bash
git clone https://github.com/shipwithian/shipkit.git
cd shipkit
```

### 2. Create and configure the environment

Create the local environment file before running setup:

```bash
cp .env.example .env
```

At minimum, review:

```env
APP_NAME="Your Project"
APP_URL=http://localhost:8000

SUPER_ADMIN_NAME="Your Name"
SUPER_ADMIN_EMAIL=you@example.com
SUPER_ADMIN_PASSWORD=use-a-unique-password
```

The example Super Admin credentials are for local setup only. Replace them before seeding a real environment or deploying the application.

SQLite is configured by default. Update the `DB_*` variables before setup if the project will use PostgreSQL, MySQL, or another supported database.

### 3. Install and initialize the application

Run the project setup script:

```bash
composer setup
```

This installs PHP and frontend dependencies, generates the application key, runs migrations, and creates a production frontend build.

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

- [ ] Create an independent repository from the GitHub template.
- [ ] Update `APP_NAME`, `APP_URL`, and project branding.
- [ ] Update the root package name, description, and keywords in `composer.json`.
- [ ] Replace template repository and documentation links in the application navigation.
- [ ] Configure the database, mail, cache, session, queue, and filesystem for the project.
- [ ] Set unique `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL`, and `SUPER_ADMIN_PASSWORD` values.
- [ ] Run migrations and seed the access-control defaults.
- [ ] Sign in as the Super Admin and verify role and permission management.
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
