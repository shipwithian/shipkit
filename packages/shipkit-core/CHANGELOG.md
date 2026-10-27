# Changelog

`shipkit-core` follows [semantic versioning](https://semver.org). A release that tightens a guard is a major version, and its entry in [UPGRADE.md](UPGRADE.md) names the failing check and the fix.

## 3.0.0 (Unreleased)

A major release because a new check can fail a project that passed 2.x. [UPGRADE.md](UPGRADE.md#upgrading-to-30) has the fix.

- **Checks:** `FrontendFileLengthTest` fails when a page in `resources/js/pages` is over 200 lines or any other frontend file is over 400, so pages stay a list of partials instead of growing into thousand-line files. The vendored `components/ui` primitives are exempt, and `shipkit.architecture.long_frontend_files` records deliberate exceptions with their reasons.
- **Guidance:** the `js` rule says how to split a file before it reaches the limit.
- **Internals:** `ProjectLayout::frontendFiles()` lists the project's own TypeScript sources, shared by both frontend checks.

## 2.0.0 (2026-10-27)

A major release because a new system permission makes a 1.0 project's seeder test fail. [UPGRADE.md](UPGRADE.md#upgrading-to-20) has the fix.

- **Access Control records:** `SystemPermission::AssignRoles` (`assign roles`), the permission to put roles on users, reserved because nobody could otherwise change who holds which role. The bootstrap creates it protected and gives it to `super-admin`; existing projects run `php artisan db:seed --class=AccessControlSeeder` again.

## 1.0.0 (2026-10-27)

The first release.

- **Shared kernel:** `DomainException`, `BulkWriteBypassesInvariants`, `GuardedBuilder`, `GuardsInvariants`, and `GuardsPivotInvariants`. Every write to a guarded model runs its guards or throws, with or without model events and for query-builder writes too.
- **Builder classification:** every public method of Laravel's Eloquent and query builders is classified as guarded, rejected, or safe, and `BuilderClassificationTest` fails on any that is not. `composer.json` allows only the Laravel major that is classified.
- **Checks:** `ArchitectureTest`, `CoreBoundaryTest`, `GuardedTablesTest`, `GuardedWritesTest`, `ProtectedMarkerTest`, `InertiaPayloadTest`, and `FrontendAbilitiesTest`, run from `vendor/` through the `ShipKit` testsuite.
- **Access Control records:** `SystemRole`, `SystemPermission`, and the bootstrap Action that is the only code allowed to create protected records.
- **Commands:** `shipkit:verify` fails when the checks are not registered or a mapped model is missing or unguarded, and `make:context` scaffolds a bounded context with its rule file.
- **Guidance:** the shared `.ai` rules, skills, and guidelines, discovered by Boost from the package.
- **Configuration:** `config/shipkit.php`, published with `php artisan vendor:publish --tag=shipkit-config`, maps the application's `Role`, `Permission`, and `User` models and records deliberate check exceptions with their reasons.
