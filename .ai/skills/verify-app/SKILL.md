---
name: verify-app
description: Drive the running ShipKit app the way a user does and capture evidence that a feature works. Use when proving a web page, form, or API endpoint behaves correctly end to end, beyond what Pest feature tests show, or when the user asks to "verify", "check it in the app", or "prove it works".
---

# Verify the app

`scripts/drive.sh` runs an isolated copy of the app: its own SQLite database under `$VERIFY_DIR` (default `$TMPDIR/shipkit-verify`), its own port (`$VERIFY_PORT`, default `8137`), migrated and seeded from scratch. It never touches the developer's `database/database.sqlite` or their running dev server. Every response it prints is also saved as evidence.

Web pages are driven through Inertia's JSON protocol: a request with `X-Inertia: true` returns `{component, props, url}` instead of HTML. That is exactly the data the React page renders, so assert on `component`, `props`, `props.errors`, and `flash`.

## Launch

```bash
.ai/skills/verify-app/scripts/drive.sh launch
```

Ready when it prints `ready at http://127.0.0.1:8137`. It refuses to start if its own instance is already running or the port is taken. The seeded super admin is `admin@example.com` / `password`, with every system permission.

## Doctor

```bash
.ai/skills/verify-app/scripts/drive.sh doctor
```

Run it first whenever anything looks off. It is read-only and reports the pid, the `/up` health check, the user count in the scratch database, and whether a session exists.

## Drive

```bash
D=.ai/skills/verify-app/scripts/drive.sh
$D login                                   # web session as the super admin
$D login someone@example.com secret        # or as another user
$D page /roles                             # Inertia page JSON
$D send POST /roles '{"name":"editor"}'    # submit a form; prints the page it redirects to
$D send DELETE /roles/1                    # props.errors holds validation and domain errors
$D api POST /api/v1/login '{"email":"admin@example.com","password":"password","device_name":"verify"}'
VERIFY_TOKEN='<token>' $D api GET /api/v1/user
```

To act as a user with specific permissions, create them in the scratch database with `php artisan tinker` using the same environment (`DB_DATABASE="$VERIFY_DIR/database.sqlite"`), then `login` as that user.

[`features/README.md`](features/README.md) maps each user-facing feature to its journey. When proving a feature, drive every entry point its map file lists.

## Evidence

Each `page`, `send`, `login`, and `api` call saves its response under `$VERIFY_DIR/evidence/` with a sequence number and prints the path. A proof:

- drives the real route a user hits, never a test-only endpoint or a direct Action call;
- captures the action and the resulting state: the response JSON **and** the database row (`sqlite3 "$VERIFY_DIR/database.sqlite" 'select ...'`);
- for a business rule, shows the refusal in `props.errors` and that the record is unchanged.

Quote the evidence file paths in your reply.

## Cleanup

```bash
.ai/skills/verify-app/scripts/drive.sh stop
```

Stops only the server this script started, by its recorded pid, and deletes the scratch database and session. Evidence stays in `$VERIFY_DIR/evidence/`.

## Gotchas

- **A stale `public/hot` file** (left by a stopped `npm run dev`) points pages at a Vite server that is not running. The instance runs with server-side rendering off, so nothing hangs, but HTML pages load without assets. `launch` warns about it. For checks in a real browser (Claude in Chrome against the launched URL), run `npm run dev` or remove `public/hot` first.
- **Login is rate limited** to 5 attempts per minute per email and IP, shared between web and API login. The limiter lives in the scratch database, so `stop` then `launch` resets it.
- **Validation and domain errors** both arrive in `props.errors` after a `send`. A domain exception reports against the field its `field()` method names, such as `role` or `permission`.
