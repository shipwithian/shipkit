---
paths:
    - 'database/seeders/**'
---

# Seeders

## Seed protected access-control defaults safely

Access-control seeders are idempotent, use the fixed `web` guard, protect system defaults, and preserve additional assignments. Rename legacy permissions without losing existing role or user assignments. Seeders go through the same model guards as every other caller: to remove a protected or assigned record, clear `is_protected` and detach its assignments first rather than bypassing model events.

## Let feature seeders own product permissions

Each feature seeds its own permissions alongside its other defaults, and grants them to the protected super-admin role there. Feature permissions do not belong in `SystemPermission` and are not protected unless they are genuinely required to administer access control and prevent a permission deadlock.

## Seed defaults only when creating

A seeder sets a default value, such as a role description, only on the record it just created (`wasRecentlyCreated`), so re-running it never overwrites what an admin has since edited. A seeder reaches guarded models only through their Eloquent models, and the guards apply to it like any other caller.
