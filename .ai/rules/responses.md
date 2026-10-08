---
paths:
    - 'app/Http/Resources/**'
    - 'app/Http/Middleware/HandleInertiaRequests.php'
---

# Responses

## List exposed fields explicitly

API Resources and Inertia props name every field sent to the client. Never pass a raw model or `toArray()`, so new columns are not exposed by default. Do not expose passwords, tokens, secrets, or internal state.

## Shape already-loaded data only

Resources and prop mapping do not query the database or trigger lazy loading. The controller, Action, or Query object loads what the response needs first.

## Version API resources

JSON API endpoints return a Resource under `app/Http/Resources/Api/V{n}`, or a purpose-built `JsonResponse` when no model is returned.
