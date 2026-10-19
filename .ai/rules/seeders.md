---
paths:
    - 'database/seeders/**'
---

# Seeders

## Seed protected access-control defaults safely

`AccessControlSeeder` only reads the super-admin credentials and calls `ShipKit\Core\AccessControl\BootstrapAccessControlAction`, which creates the missing protected records with the fixed `web` guard and keeps additional assignments. No seeder writes `is_protected`: it is set when bootstrap creates a record and never changes, so a protected record is never renamed, unprotected, or deleted by a seeder. Seeders go through the same model guards as every other caller.

## Let feature seeders own product permissions

Each feature seeds its own permissions alongside its other defaults, and grants them to the protected super-admin role there. Feature permissions do not belong in `SystemPermission` and are not protected unless they are genuinely required to administer access control and prevent a permission deadlock.

## Seed defaults only when creating

A seeder sets a default value, such as a role description, only on the record it just created (`wasRecentlyCreated`), so re-running it never overwrites what an admin has since edited. A seeder reaches guarded models only through their Eloquent models, and the guards apply to it like any other caller.
