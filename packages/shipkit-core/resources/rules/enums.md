---
paths:
    - 'app/Domain/*/Enums/**'
---

# Enums

## Reserve SystemPermission for access-control bootstrap

Reserve `SystemPermission` (`ShipKit\Core\AccessControl\SystemPermission`, owned by `shipkit-core`) for the protected permissions without which nobody could recover or change who may do what: managing roles, managing permissions, and assigning permissions or roles. A permission that merely belongs to access control does not qualify: viewing an audit log, exporting a report, or any other feature permission is an ordinary permission, even when an access-control feature introduces it.

Ordinary permissions belong to their owning feature's seeder, which creates the permission and grants it to the protected super-admin role with `givePermissionTo()`.

`vendor/shipwithian/shipkit-core/tests/BootstrapAccessControlActionTest.php` pins the list of cases. Adding one is a `shipkit-core` change, made in ShipKit with the reason it qualifies, never in a project's `vendor/`.
