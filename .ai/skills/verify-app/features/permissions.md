# Permissions

## Sub-features

- List permissions with role and user counts.
- Create, rename, and delete a permission.
- Protected permissions (the `SystemPermission` cases) cannot be renamed or deleted.
- A permission assigned to any role or user cannot be deleted.

## How to get to it (user POV)

Settings → Permissions (`/settings/permissions`), visible with `manage permissions resource`.

## Driving it with drive.sh

```bash
D=.ai/skills/verify-app/scripts/drive.sh
$D login
$D page /settings/permissions                                       # component permissions/index
$D send POST /settings/permissions '{"name":"edit posts"}'          # created; flash toast
ID=$(sqlite3 "$VERIFY_DIR/database.sqlite" "select id from permissions where name='assign permissions'")
$D send DELETE "/settings/permissions/$ID"                          # props.errors.permission "Protected permissions cannot be deleted."
```

## Gotchas

- Every seeded permission is protected and assigned to `super-admin`, so in-use deletion needs a new permission attached to a role first (`send PUT /settings/roles/{id}/permissions '{"permissions":[<id>]}'`).
