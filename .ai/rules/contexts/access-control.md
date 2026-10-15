---
paths:
    - 'app/Domain/AccessControl/**'
---

# Access Control context

## Vocabulary

- **Role**: a named set of permissions assigned to users (`Models\Role`, extends Spatie).
- **Permission**: a named ability checked by policies through `$user->can()` (`Models\Permission`, extends Spatie).
- **Protected record**: a role, permission, or user with `is_protected = true`, created only by `AccessControlSeeder`.
- **System permission**: a `SystemPermission` case required to administer access control.
- **Assignable permission**: a permission that may be synced onto a role.

## Invariants

Aggregate rules run as `updating`/`deleting` guards registered in each model's `booting()`, so they hold for every caller.

| Rule                                                      | Enforced by                                                          | Exception                        |
| --------------------------------------------------------- | -------------------------------------------------------------------- | -------------------------------- |
| A protected role or permission cannot be renamed          | `Role::ensureCanBeRenamedTo()`, `Permission::ensureCanBeRenamedTo()` | `ProtectedRecordCannotBeChanged` |
| A protected role or permission cannot be deleted          | `Role::ensureCanBeDeleted()`, `Permission::ensureCanBeDeleted()`     | `ProtectedRecordCannotBeChanged` |
| A role assigned to users cannot be deleted                | `Role::ensureCanBeDeleted()`                                         | `RoleIsInUse`                    |
| A permission assigned to roles or users cannot be deleted | `Permission::ensureCanBeDeleted()`                                   | `PermissionIsInUse`              |
| A protected role always keeps every protected permission  | `SyncRolePermissionsAction`                                          | — (merged silently)              |

Use the fixed `web` guard for all roles and permissions.

## Public surface

- Actions: `CreateRoleAction`, `UpdateRoleAction`, `DeleteRoleAction`, `CreatePermissionAction`, `UpdatePermissionAction`, `DeletePermissionAction`, `SyncRolePermissionsAction`.
- Queries: `RoleListQuery`, `PermissionListQuery`, `AssignablePermissionListQuery`.
