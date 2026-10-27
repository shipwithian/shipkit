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
