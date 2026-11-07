# Upgrade notes

`shipkit-core` follows [semantic versioning](https://semver.org), and its major version is a promise about what your CI will do. A release that tightens a guard, so that code which passed before can now fail a check, is a **major** release. Minor and patch releases never make a passing project fail.

Every major release has a section below named for the version, and each entry in it names three things:

- **Fails:** the check or exception that now stops you, exactly as it appears (the test name or the exception class and message), so you can search for it.
- **Fix:** what to change in your project.
- **Why:** the hole the change closes, in one sentence.

Add the section in the pull request that makes the change, not afterwards. A major release without one is not ready.

```md
## Upgrading to 2.0

### A check or exception, named as it appears

- **Fails:** `GuardedTablesTest` › "never writes through the base query builder"
- **Fix:** Load the model, change it, and call `save()` instead of writing through `toBase()`.
- **Why:** A write through the base builder skipped every guard on the model.
```

## Upgrading to 4.0

### Laravel's standard folders

- **Fails:** `ArchitectureTest` › "keeps each kind of class in its standard folder", with `app/Domain exists.`
- **Fix:** Move each context's classes to Laravel's folder for their kind, and rename their namespaces to match:

    | 3.x                                                             | 4.0                                                                                                     |
    | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
    | `app/Domain/{Context}/Actions` (`App\Domain\{Context}\Actions`) | `app/Actions/{Feature}` (`App\Actions\{Feature}`)                                                       |
    | `app/Domain/{Context}/Queries`                                  | `app/Queries/{Feature}`                                                                                 |
    | `app/Domain/{Context}/Models`                                   | `app/Models`                                                                                            |
    | `app/Domain/{Context}/Policies`                                 | `app/Policies` (drop `#[UsePolicy]` where the name matches the model)                                   |
    | `app/Domain/{Context}/Exceptions`                               | `app/Exceptions`                                                                                        |
    | `.ai/rules/contexts/{context}.md`                               | `.ai/rules/features/{feature}.md`, with `paths` pointing at the new folders                             |
    | `database/factories/Domain/{Context}/Models/{Model}Factory.php` | `database/factories/{Model}Factory.php`, and remove the factory-name resolver from `AppServiceProvider` |

    In `config/shipkit.php`, rename `contexts_using_framework_exceptions` to `features_using_framework_exceptions` and `non_context_folders` to `non_feature_folders`, and point `models` at `App\Models`. Run `php artisan make:feature {Name}` instead of `make:context`.

- **Why:** The bounded-context layout added a second vocabulary and special cases (the shared `User`, Fortify's actions) on top of Laravel, while the protection came from Actions, Query objects, and model guards, which work the same in Laravel's own folders.

## Upgrading to 3.0

### Frontend files over the length limit

- **Fails:** `FrontendFileLengthTest` › "keeps every frontend file short enough to read in one sitting", with `resources/js/pages/{feature}/{page}.tsx has N lines, over the 200-line limit for a page.`
- **Fix:** Split the file: move each section, dialog, table, or form into its own partial in the feature's `partials/` folder, and state and handlers into a `use-{feature}.ts` hook beside it. Any other frontend file has a 400-line limit. To keep a file long on purpose, list its path in `architecture.long_frontend_files` in `config/shipkit.php` with the reason.
- **Why:** Nothing limited a page's size, so pages grew past 2,000 lines that neither people nor agents could review.

## Upgrading to 2.0

### The `assign roles` system permission

- **Fails:** `AccessControlSeederTest` › "seeds protected access control records idempotently", with `Failed asserting that 4 is identical to 3.`
- **Fix:** Count the system permissions instead of hardcoding them: `->toBe(count(SystemPermission::cases()))`. Then run `php artisan db:seed --class=AccessControlSeeder` in every environment so the database has the new permission and `super-admin` holds it.
- **Why:** `SystemPermission::AssignRoles` was added, so the bootstrap now creates four protected permissions instead of three.

### `SystemRecordIsNotProtected` when seeding

- **Fails:** `AccessControlSeeder` throws `SystemRecordIsNotProtected` for the permission `assign roles`.
- **Fix:** Your project already has its own unprotected `assign roles` permission. Rename it or delete it, then run the seeder again. Roles that held it need the new protected permission instead.
- **Why:** Only the bootstrap may create a protected record, and it refuses to adopt an existing unprotected one.

### The Users page is template code

- **Fails:** nothing. Updating the package adds the permission, not the feature.
- **Fix:** To assign roles from the app, copy the feature from the ShipKit template: `SyncUserRolesAction`, `RoleExceedsActorPermissions`, `UserRoleListQuery`, `RolePolicy::assignToUsers`, `UserRoleController` and its request, props, and routes, `resources/js/pages/users`, the `viewUsers` navigation ability, and their tests. Then run `composer types:generate`.
- **Why:** Projects are one-time copies of the template, and only enforcement ships in `shipkit-core`.

## Upgrading to 1.0

The first release. There is nothing to upgrade from.
