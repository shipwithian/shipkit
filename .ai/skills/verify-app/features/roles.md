# Roles

## Sub-features

- List roles with permission and user counts.
- Create, rename, and delete a role.
- Assign permissions to a role.
- Protected roles (the seeded `super-admin`) cannot be renamed or deleted, and always keep every protected permission.
- A role assigned to users cannot be deleted.

## How to get to it (user POV)

Sidebar → Roles (`/roles`), visible with `manage roles resource` or `assign permissions`. Each row's permissions action opens `/roles/{role}/permissions`.

## Driving it with drive.sh

```bash
D=.ai/skills/verify-app/scripts/drive.sh
$D login
$D page /roles                                     # component roles/index; props.roles[].name
$D send POST /roles '{"name":"editor"}'            # flash.toast.message "Role created."
$D send POST /roles '{"name":""}'                  # props.errors.name
ID=$(sqlite3 "$VERIFY_DIR/database.sqlite" "select id from roles where name='super-admin'")
$D send PUT "/roles/$ID" '{"name":"renamed"}'      # props.errors.name "Protected roles cannot be renamed."
$D send DELETE "/roles/$ID"                        # props.errors.role "Protected roles cannot be deleted."
$D page "/roles/$ID/permissions"                   # props.permission_ids
```

Proof of a refusal: the error in `props.errors` **and** `sqlite3 "$VERIFY_DIR/database.sqlite" "select name from roles"` still showing the role.

## Gotchas

- `$VERIFY_DIR` defaults to `$TMPDIR/shipkit-verify`; export it before using `sqlite3` paths.
- Deleting a role assigned to users needs a second user holding it; create one with tinker against the scratch database first.
