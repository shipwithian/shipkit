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

## Shape API responses consistently

A collection returns the resource collection under `data`. A create returns 201 with the resource under `data`, and any one-time secret next to it through `additional()`, never inside the resource: a resource never lists a token, password, or secret, and `ArchitectureTest` fails when one does. A delete returns 204.

## Paginate Inertia pages with a list and a pagination prop

The Query returns a paginator with `withQueryString()`. The controller maps `$paginator->items()` into a list prop named for the resource (`entries`) and adds a `pagination` prop with `current_page`, `last_page`, `total`, `previous_page_url`, and `next_page_url`. The page's types live in `resources/js/types/{feature}.ts`.
