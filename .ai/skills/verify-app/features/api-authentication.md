# API authentication

## Sub-features

- Token login for a device (`device_name`), returning a Sanctum token.
- Two-factor challenge instead of a token when the user has two-factor enabled.
- Current user with permissions; logout revokes the current token.
- Registration, email verification, and password reset over the API.

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

## Gotchas

- Login shares the 5-per-minute limiter with web login; after several web logins the API returns 429. `stop` then `launch` resets it.
- Responses include stack traces while `APP_DEBUG=true`; quote only the status line and the relevant body fields as evidence.
