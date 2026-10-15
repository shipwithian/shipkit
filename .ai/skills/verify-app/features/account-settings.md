# Account settings

## Sub-features

- Update profile name and email; changing the email clears its verification.
- Change password (requires the current password).
- Delete the account (requires the password); protected accounts cannot be deleted.
- Two-factor authentication and passkeys on the security page.

## How to get to it (user POV)

User menu → Settings → Profile (`/settings/profile`) or Security (`/settings/security`).

## Driving it with drive.sh

Run the password and delete journeys before changing the email: an email change unverifies the account, and the `verified` middleware then redirects those routes to `/email/verify`.

```bash
D=.ai/skills/verify-app/scripts/drive.sh
$D login
$D page /settings/profile                                   # component settings/profile
$D send PUT /settings/password '{"current_password":"password","password":"new-password-123","password_confirmation":"new-password-123"}'
sqlite3 "$VERIFY_DIR/database.sqlite" "select password from users"   # hash differs from before
$D send PUT /settings/password '{"current_password":"wrong","password":"x","password_confirmation":"x"}'   # props.errors.current_password, .password
$D send DELETE /settings/profile '{"password":"new-password-123"}'   # 403: the seeded admin is protected; user count unchanged
$D send PATCH /settings/profile '{"name":"Admin","email":"new@example.com"}'
sqlite3 "$VERIFY_DIR/database.sqlite" "select email, email_verified_at from users"   # email_verified_at is NULL
```

## Gotchas

- The seeded admin is protected, so `DELETE /settings/profile` returns 403 from `UserPolicy` before the domain guard runs. To prove a successful deletion, create an unprotected, verified user with tinker against the scratch database and log in as them.
- After an email change the account is unverified: password, security, and delete routes redirect to `/email/verify` until it is verified again.
- Changing the admin's email changes the login for the rest of the run.
