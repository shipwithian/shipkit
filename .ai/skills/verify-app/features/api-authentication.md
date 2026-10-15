# API authentication

## Sub-features

- Token login for a device (`device_name`), returning a Sanctum token.
- Two-factor challenge instead of a token when the user has two-factor enabled.
- Current user with permissions; logout revokes the current token.
- Registration, email verification, and password reset over the API.
- Token management: list my tokens, create a named token (the secret is shown once), and revoke any of my tokens from any device.

## How to get to it (user POV)

A mobile or third-party client calls `/api/v1/*` with `Accept: application/json`.

## Driving it with drive.sh

```bash
D=.ai/skills/verify-app/scripts/drive.sh
$D api POST /api/v1/login '{"email":"admin@example.com","password":"password","device_name":"verify"}'   # 200; body.token
VERIFY_TOKEN='<token>' $D api GET /api/v1/user      # 200; data.permissions
$D api GET /api/v1/user                             # 401 without a token
VERIFY_TOKEN='<token>' $D api POST /api/v1/logout   # 204; the token stops working
```

Token management, with `T1` from the login above:

```bash
VERIFY_TOKEN="$T1" $D api POST /api/v1/tokens '{"name":"phone"}'   # 201; body.data has no secret, body.token is the secret
VERIFY_TOKEN="$T1" $D api GET /api/v1/tokens                       # 200; data lists safe fields only, no secret anywhere
VERIFY_TOKEN="$T1" $D api DELETE /api/v1/tokens/<id>               # 204; the revoked token's next request is 401
```

Another user's token answers 404: log in as a second user (create one with tinker against the scratch database) and `DELETE` the first user's token id; the row stays in `personal_access_tokens`.

## Gotchas

- Login shares the 5-per-minute limiter with web login; after several web logins the API returns 429. `stop` then `launch` resets it.
- Responses include stack traces while `APP_DEBUG=true`; quote only the status line and the relevant body fields as evidence.
