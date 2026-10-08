---
paths:
    - 'app/Policies/**'
---

# Policies

## Authorize exclusively through permissions

Policies map abilities to `$user->can()` checks. Do not authorize by role name and do not add a Super Admin or other role-based bypass.

## Separate resource and workflow permissions

Use `manage {plural resource} resource` for standard resource CRUD abilities. Give non-CRUD policy abilities their own verb permissions, with only the minimum supporting read access they require.

## Allow identity checks for self-service abilities

Abilities that act on the authenticated user's own account, such as viewing or updating their profile, may compare identity with `$user->is($model)` instead of a permission. Every other ability stays permission-based.
