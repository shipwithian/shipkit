# Changelog

`shipkit-core` follows [semantic versioning](https://semver.org). A release that tightens a guard is a major version, and its entry in [UPGRADE.md](UPGRADE.md) names the failing check and the fix.

## Unreleased

Released as 5.0.0 because `shipkit-core` shares the template's version, which moves to a new major with the switch from Sanctum to Passport. Core's new checks only look at MCP tools, so nothing in it can fail a project that passed 4.x.

- **Guidance:** the `tests` rule's note about calling `Auth::forgetGuards()` before reusing a revoked bearer token no longer names Sanctum.
- **Upgrade notes:** [UPGRADE.md](UPGRADE.md#upgrading-to-50) carries the template's Sanctum → Passport steps.
- **Checks:** `ArchitectureTest` gains "follows the MCP tool conventions": a tool in `app/Mcp/Tools` authorizes first, uses no `DB` or model queries, declares an output schema, and confirms before acting when it is `#[IsDestructive]`. The standard-folders check keeps tools in `app/Mcp/Tools/{Feature}`. Both pass on a project without tools.
- **Channel:** `ShipKit\Core\Channel` names where a change came from (web, API, or MCP with its client), for Actions that record it.
- **Commands:** `make:feature {Name} --mcp` also creates `app/Mcp/Tools/{Name}`.
- **Guidance:** the `mcp` rule covers confirming one risky argument of an otherwise harmless tool, and the allowed redirects and self-declared client names of AI clients.
- **Guidance:** a new `mcp` rule; `new-feature` decides whether a feature gets tools, `to-spec` gains an "AI Client Access" section, and `grilling` raises AI-client access for new features.

## 4.0.0 (2026-11-07)

A major release because the architecture checks now expect Laravel's standard folders instead of `app/Domain/{Context}`. [UPGRADE.md](UPGRADE.md#upgrading-to-40) has the map.

- **Checks:** `ArchitectureTest` discovers features from the folders of `app/Actions` and `app/Queries` (except `app/Actions/Fortify`) instead of bounded contexts in `app/Domain`. A new check, "keeps each kind of class in its standard folder", fails on an `app/Domain` folder, an Action outside `app/Actions/{Feature}`, a Query outside `app/Queries/{Feature}`, or an Eloquent model outside `app/Models`. The context-boundary checks are gone; the shape, controller, request, response, policy, actor, and guard checks are unchanged apart from where they look.
- **Configuration:** `architecture.contexts_using_framework_exceptions` is now `architecture.features_using_framework_exceptions`, and `architecture.non_context_folders` is now `architecture.non_feature_folders`.
- **Commands:** `php artisan make:feature {Name}` replaces `make:context`. It creates `app/Actions/{Name}`, `app/Queries/{Name}`, `tests/Feature/{Name}`, the rule file `.ai/rules/features/{name}.md`, and its index row.
- **Guidance:** the architecture guideline, rules, and skills describe the standard layout in plain Laravel terms. Feature rule files live in `.ai/rules/features/`, the `new-context` skill is now `new-feature`, and the factories rule follows Laravel's default factory naming.

## 3.1.0 (2026-11-06)

- **Development:** `composer dev` runs [Mailpit](https://mailpit.axllent.org) as its `mail` process in a local environment where the `mailpit` binary is installed. To send local mail to it, set `MAIL_MAILER=smtp` and `MAIL_PORT=1025` in `.env`; read it at http://localhost:8025. Set `dev.mailpit` to `false` in a published `config/shipkit.php`, or call `DevCommands::except('mail')`, to turn it off.
- **Guidance:** the `responses` rule describes the generated-types check as a comparison with the project's own copy of the file, since `composer ci:check` no longer needs git.

## 3.0.0 (2026-10-27)

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
