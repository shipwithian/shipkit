#!/usr/bin/env bash
# Drive an isolated ShipKit instance over HTTP, the way the browser does.
#
# Usage: drive.sh <command> [args]
#   launch                      Fresh SQLite database, migrate + seed, serve on $VERIFY_PORT.
#   doctor                      Read-only health check of the instance this script started.
#   login [email] [password]    Start a web session (defaults to the seeded super admin).
#   page <path>                 GET an Inertia page; prints {component, props, url} JSON.
#   send <METHOD> <path> [json] Submit like an Inertia form; follows the redirect and prints
#                               the resulting page JSON (validation errors are in props.errors).
#   api <METHOD> <path> [json]  Call the JSON API; prints the status line and body.
#                               Set VERIFY_TOKEN to send a Sanctum bearer token.
#   stop                        Stop the server and delete the scratch database. Evidence stays.
#
# Every response is also saved under $VERIFY_DIR/evidence/ with a sequence number.

set -euo pipefail

VERIFY_PORT="${VERIFY_PORT:-8137}"
VERIFY_DIR="${VERIFY_DIR:-${TMPDIR:-/tmp}/shipkit-verify}"
VERIFY_DIR="${VERIFY_DIR%/}"
BASE_URL="http://127.0.0.1:${VERIFY_PORT}"
JAR="$VERIFY_DIR/cookies.txt"
EVIDENCE="$VERIFY_DIR/evidence"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"

export APP_URL="$BASE_URL"
export DB_CONNECTION=sqlite
export DB_DATABASE="$VERIFY_DIR/database.sqlite"
export MAIL_MAILER=log
export INERTIA_SSR_ENABLED=false

mkdir -p "$EVIDENCE"

save() {
  local name="$1" file
  file="$EVIDENCE/$(printf '%03d' "$(find "$EVIDENCE" -type f | wc -l | tr -d ' ')")-${name//[^A-Za-z0-9_.-]/_}"
  cat > "$file"
  cat "$file"
  echo >&2
  echo "evidence: $file" >&2
}

xsrf() {
  awk '$6 == "XSRF-TOKEN" { print $7 }' "$JAR" 2>/dev/null | php -r 'echo urldecode(trim(stream_get_contents(STDIN)));'
}

inertia_version() {
  (cd "$PROJECT_ROOT" && php artisan tinker --execute 'echo app(App\Http\Middleware\HandleInertiaRequests::class)->version(request());')
}

# Submit like the Inertia client: send the request, then GET the redirect target as a page.
submit() {
  local method="$1" path="$2" json="$3" result status location
  result="$(inertia_curl -X "$method" -H 'Content-Type: application/json' -d "$json" \
    -o "$VERIFY_DIR/last-body" -w '%{http_code} %{redirect_url}' "$BASE_URL$path")"
  status="${result%% *}"
  location="${result#* }"
  echo "$method $path -> $status${location:+ -> $location}" >&2
  if [[ "$status" =~ ^30[1-3]$ && -n "$location" ]]; then
    inertia_curl "$location"
  else
    cat "$VERIFY_DIR/last-body"
  fi
}

start_session() {
  rm -f "$JAR"
  inertia_curl -o /dev/null "$BASE_URL/login"
}

inertia_curl() {
  curl -s -b "$JAR" -c "$JAR" \
    -H 'Accept: text/html, application/xhtml+xml' \
    -H 'X-Requested-With: XMLHttpRequest' \
    -H 'X-Inertia: true' \
    -H "X-Inertia-Version: $(cat "$VERIFY_DIR/version" 2>/dev/null)" \
    -H "X-XSRF-TOKEN: $(xsrf)" \
    "$@"
}

case "${1:-}" in
  launch)
    if [[ -f "$VERIFY_DIR/server.pid" ]] && kill -0 "$(cat "$VERIFY_DIR/server.pid")" 2>/dev/null; then
      echo "already running (pid $(cat "$VERIFY_DIR/server.pid")) at $BASE_URL; run 'stop' first" >&2
      exit 1
    fi
    if lsof -nP -iTCP:"$VERIFY_PORT" -sTCP:LISTEN >/dev/null 2>&1; then
      echo "port $VERIFY_PORT is in use by another process; set VERIFY_PORT" >&2
      exit 1
    fi
    if [[ -f "$PROJECT_ROOT/public/hot" ]] && ! curl -s -o /dev/null --max-time 2 "$(cat "$PROJECT_ROOT/public/hot")"; then
      echo "warning: public/hot points at a Vite server that is not running; HTML pages will load without assets." >&2
      echo "         JSON driving (page/send/api) is unaffected. For browser checks, run 'npm run dev' or remove public/hot." >&2
    fi
    rm -f "$DB_DATABASE" "$JAR" "$VERIFY_DIR/version"
    touch "$DB_DATABASE"
    (cd "$PROJECT_ROOT" && php artisan migrate --seed --force --no-interaction >/dev/null)
    # Detach fully: no inherited stdin/stdout/stderr, so callers reading this script's output never wait on the server.
    (cd "$PROJECT_ROOT" && exec nohup php artisan serve --host=127.0.0.1 --port="$VERIFY_PORT") </dev/null >"$VERIFY_DIR/server.log" 2>&1 &
    echo $! >"$VERIFY_DIR/server.pid"
    for _ in $(seq 1 50); do
      if [[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/up")" == "200" ]]; then
        inertia_version >"$VERIFY_DIR/version"
        start_session
        echo "ready at $BASE_URL (pid $(cat "$VERIFY_DIR/server.pid"), database $DB_DATABASE)"
        exit 0
      fi
      sleep 0.2
    done
    echo "server did not become ready; see $VERIFY_DIR/server.log" >&2
    exit 1
    ;;

  doctor)
    pid="$(cat "$VERIFY_DIR/server.pid" 2>/dev/null || true)"
    [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null || { echo "not running: no live pid in $VERIFY_DIR/server.pid" >&2; exit 1; }
    code="$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/up")"
    [[ "$code" == "200" ]] || { echo "health check returned $code" >&2; exit 1; }
    users="$(sqlite3 "$DB_DATABASE" 'select count(*) from users;' 2>/dev/null || echo '?')"
    echo "ok: pid $pid, $BASE_URL/up 200, $users user(s) in $DB_DATABASE, session: $([[ -s "$JAR" ]] && echo yes || echo none)"
    ;;

  login)
    email="${2:-admin@example.com}"
    password="${3:-password}"
    start_session
    submit POST /login "{\"email\":\"$email\",\"password\":\"$password\"}" | save "login.json"
    ;;

  page)
    inertia_curl "$BASE_URL${2:?path required}" | save "page${2//\//_}.json"
    ;;

  send)
    method="${2:?METHOD required}"
    path="${3:?path required}"
    submit "$method" "$path" "${4:-{\}}" | save "send-${method}${path//\//_}.json"
    ;;

  api)
    method="${2:?METHOD required}"
    path="${3:?path required}"
    auth=()
    [[ -n "${VERIFY_TOKEN:-}" ]] && auth=(-H "Authorization: Bearer $VERIFY_TOKEN")
    curl -s -i -X "$method" -H 'Accept: application/json' -H 'Content-Type: application/json' \
      "${auth[@]+"${auth[@]}"}" ${4:+-d "$4"} "$BASE_URL$path" | save "api-${method}${path//\//_}.txt"
    ;;

  stop)
    if [[ -f "$VERIFY_DIR/server.pid" ]]; then
      pid="$(cat "$VERIFY_DIR/server.pid")"
      pkill -P "$pid" 2>/dev/null || true
      kill "$pid" 2>/dev/null || true
      rm -f "$VERIFY_DIR/server.pid"
    fi
    rm -f "$DB_DATABASE" "$JAR" "$VERIFY_DIR/version"
    echo "stopped; evidence kept in $EVIDENCE"
    ;;

  *)
    sed -n '2,17p' "$0"
    exit 1
    ;;
esac
