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
- **Super admin**: a user holding the `super-admin` role (`SystemRole::SuperAdmin`).
- **Role permission** and **role assignment**: the pivot rows that give a role a permission (`Models\RolePermission`) and a user a role (`Models\RoleAssignment`). They carry the relationship guards.

## Invariants

Aggregate rules run from each model's `guardUpdate()`/`guardDelete()` through `GuardsInvariants`, so they hold for every caller.

| Rule                                                      | Enforced by                                                                                                  | Exception                        |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | -------------------------------- |
| A protected role or permission cannot be renamed          | `Role::ensureCanBeRenamedTo()`, `Permission::ensureCanBeRenamedTo()`                                         | `ProtectedRecordCannotBeChanged` |
| A protected role or permission cannot be deleted          | `Role::ensureCanBeDeleted()`, `Permission::ensureCanBeDeleted()`                                             | `ProtectedRecordCannotBeChanged` |
| A role assigned to users cannot be deleted                | `Role::ensureCanBeDeleted()`                                                                                 | `RoleIsInUse`                    |
| A permission assigned to roles or users cannot be deleted | `Permission::ensureCanBeDeleted()`                                                                           | `PermissionIsInUse`              |
| A protected role always keeps every protected permission  | `RolePermission::ensureCanBeDeleted()` on every detach, revoke, and sync                                     | `ProtectedRecordCannotBeChanged` |
| The last super admin cannot lose the role or be deleted   | `RoleAssignment::ensureCanBeDeleted()`, `User::ensureCanBeDeleted()`, both via `Role::ensureCanLoseHolder()` | `LastSuperAdminCannotBeRemoved`  |

Relationship rules sit on the pivot models, which `Role::permissions()`, `Permission::roles()`, `Role::users()`, and `User::roles()` attach through. `SyncRolePermissionsAction` adds a protected role's protected permissions back before syncing so the UI never trips the guard.

Use the fixed `web` guard for all roles and permissions.

## Gotchas

- `syncPermissions()` and `syncRoles()` detach everything first, so they throw for a protected role or the sole super admin even when the same records are re-attached. Sync the difference with `permissions()->sync($ids)` or `roles()->sync($ids)`, as `SyncRolePermissionsAction` does.
- `Role` and `Permission` extend Spatie models, which accept mass assignment of every column. Accept only validated fields from a Form Request when creating or updating them.

## Requirements for features built on this context

- **Privilege escalation.** A feature that lets someone assign roles must not let them grant permissions they do not hold themselves (for example by granting `super-admin` to themselves). Check that the roles being granted hold only permissions the actor already has, and test it. ShipKit ships no role-assignment UI, so nothing enforces this yet.
- **Concurrency.** A guard that counts what remains ("another super admin exists") locks those rows with `lockForUpdate()`; `GuardsInvariants` runs the guard and the write in one transaction so the lock holds.

## Public surface

- Actions: `CreateRoleAction`, `UpdateRoleAction`, `DeleteRoleAction`, `CreatePermissionAction`, `UpdatePermissionAction`, `DeletePermissionAction`, `SyncRolePermissionsAction`.
- Queries: `RoleListQuery`, `PermissionListQuery`, `AssignablePermissionListQuery`.
- Exceptions: `ProtectedRecordCannotBeChanged`, `RoleIsInUse`, `PermissionIsInUse`, `LastSuperAdminCannotBeRemoved`.
