---
paths:
    - 'database/seeders/**'
---

# Seeders

## Seed protected access-control defaults safely

Access-control seeders are idempotent, use the fixed `web` guard, protect system defaults, and preserve additional assignments. Rename legacy permissions without losing existing role or user assignments.

## Let feature seeders own product permissions

Each feature seeds its own permissions alongside its other defaults. Feature permissions do not belong in `SystemPermission` and are not protected unless they are genuinely required to administer access control and prevent a permission deadlock.
