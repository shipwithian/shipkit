---
paths:
    - 'app/Policies/**'
---

# Policies

## Authorize exclusively through permissions

Policies map abilities to `$user->can()` checks. Do not authorize by role name and do not add a Super Admin or other role-based bypass.

## Separate resource and workflow permissions

Use `manage {plural resource} resource` for standard resource CRUD abilities. Give non-CRUD policy abilities their own verb permissions, with only the minimum supporting read access they require.
