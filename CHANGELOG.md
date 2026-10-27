# Changelog

ShipKit follows [semantic versioning](https://semver.org). A project created from ShipKit is a one-time copy and starts its own version sequence; the enforcement that must keep improving after creation lives in [`shipwithian/shipkit-core`](packages/shipkit-core/CHANGELOG.md), which projects update with Composer.

## Unreleased

Requires `shipwithian/shipkit-core` 3.0.

- **Short frontend files:** pages stay at or under 200 lines and other frontend files at or under 400, checked by `shipkit-core`'s `FrontendFileLengthTest`, so a page composes partials instead of growing into one large file.

## 2.0.0 (2026-10-27)

Requires `shipwithian/shipkit-core` 2.0.

- **Assign roles to users:** a paginated Users page, gated by the new `assign roles` system permission, sets the roles each user holds. `SyncUserRolesAction` refuses to add or remove a role holding a permission the actor lacks, so nobody can grant themselves `super-admin` or strip it from someone with more access, and the last super admin still cannot lose the role.

## 1.0.0 (2026-10-27)

The first release that lets agents implement features unattended: the rules that protect the application's invariants are enforced by code, tests, and CI instead of by review, and they ship in a versioned package that updates with `composer update`.

There is no upgrade path from 0.x. ShipKit had no users before 1.0.0, so nothing in this release carries compatibility code. Create new projects from 1.0.0.

### Enforcement moves into `shipwithian/shipkit-core`

- The guard traits, domain exception base class, architecture checks, guarded-table and cascade checks, `make:context`, and the system roles and permissions live in the package, not in your project. `composer update` brings improved checks to a project that already exists.
- `config/shipkit.php` maps the project's `Role`, `Permission`, and `User` models. The application refuses to boot when a mapped model does not use `GuardsInvariants`.
- `php artisan shipkit:verify` runs in `composer ci:check` and fails when core's testsuite is not registered in `phpunit.xml`, so deleting one line cannot silently turn the checks off.
- The shared `.ai` rules, skills, and guidelines come from the package through Boost (`php artisan boost:update`) instead of being copied into each project.

### Guarded writes fail closed

- Every query-builder write on a guarded model throws, even for one row, and so do the inherited ones (`increment`, `decrement`, `touch`, `forceDelete`, `updateOrInsert`, and the rest). Every Eloquent builder method is classified as guarded, rejected, or safe, and a test fails when a Laravel release adds one that is unclassified.
- `is_protected` cannot change after a record is created, in either direction. One Access Control bootstrap Action, called by the seeder, is the only code that creates protected records, and a source check fails on any other file that writes the flag.
- The last-super-admin guard is proven under real contention on MySQL and Postgres, in addition to the SQLite suite.

### The frontend contract is generated from PHP

- Every Inertia page and the shared props are typed PHP classes. `composer types:generate` writes the TypeScript types to `resources/js/types/generated.ts`, and `composer ci:check` fails when the committed file differs. This adds the `spatie/laravel-typescript-transformer` dependency.
- Pages receive abilities computed with the Gate from the policy abilities their controllers authorize (`can.create`, a `can` on each row, `auth.can` for navigation). The UI no longer reads permission names, `auth.permissions` is no longer shared, and a core check fails on `permissions.includes(...)` in `resources/js`.

### Agent workflow

- `.github/CODEOWNERS` assigns the maintainer to the checks and to the places their exceptions are recorded. The `implement` and `review-diff` skills state the evidence an agent needs to merge project-owned code: a regression test shown failing without the change, a green `composer ci:check`, and an independent review. [`docs/branch-protection.md`](docs/branch-protection.md) records the repository rules that enforce the split.
- A CI job creates a project from the installer archive with the package from the same commit and runs `composer ci:check` in it, including the two cases that must fail.

### Also since 0.1.0

- Code is organized into DDD-lite bounded contexts (`AccessControl`, `Settings`, `Authentication`) with `make:context` to add more, enforced by architecture tests.
- API token management endpoints for Sanctum.
- Agent skills are generated when a project is created, and the `verify-app` skill drives an isolated copy of the app.
- `league/commonmark` is updated for two security advisories.
