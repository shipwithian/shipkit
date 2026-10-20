---
paths:
    - 'app/Http/Resources/**'
    - 'app/Http/Props/**'
    - 'app/Http/Middleware/HandleInertiaRequests.php'
---

# Responses

## List exposed fields explicitly

API Resources and Inertia props name every field sent to the client. Never pass a raw model or `toArray()`, so new columns are not exposed by default. Do not expose passwords, tokens, secrets, or internal state.

## Declare Inertia payloads as typed props classes

Every page with props of its own receives a `{Page}Props` class from `app/Http/Props/{Context}/`: a `final readonly` class marked `#[TypeScript]` that extends `App\Http\Props\PageProps`, whose public promoted properties are the props. Nested records are their own `#[TypeScript]` classes with a static `from({Model})` that maps the loaded model, and lists are typed `list<{Item}>` in the constructor's PHPDoc, built with `array_values(array_map({Item}::from(...), ...))`. Shared props are `SharedProps`, returned by `HandleInertiaRequests::share()`. Type each property as what the server sends: nullable when it can be null, and no optional or catch-all fields. `InertiaPayloadTest` fails on an `Inertia::render()`, `inertia()`, or `Route::inertia()` that passes props any other way.

`composer types:generate` writes the TypeScript types to `resources/js/types/generated.ts`. Run it after changing a props class and commit the result; `composer ci:check` regenerates the file and fails when it differs from what is committed.

## Send abilities as typed props

Abilities are `#[TypeScript]` classes of booleans named for the policy abilities they mirror: `{Page}Abilities` as the page's `can` prop for page-level controls such as `create`, `{Model}Abilities` as a list item's `can` for row controls such as `update` and `delete`, and `NavigationAbilities` as `auth.can` in the shared props. The controller, or `HandleInertiaRequests` for shared props, computes them with the Gate and passes them in; a list item's `from()` takes its abilities as a second argument, so the list is built with a closure. Prop classes never call the Gate themselves.

## Shape already-loaded data only

Resources and prop mapping do not query the database or trigger lazy loading. The controller, Action, or Query object loads what the response needs first.

## Version API resources

JSON API endpoints return a Resource under `app/Http/Resources/Api/V{n}`, or a purpose-built `JsonResponse` when no model is returned.

## Shape API responses consistently

A collection returns the resource collection under `data`. A create returns 201 with the resource under `data`, and any one-time secret next to it through `additional()`, never inside the resource: a resource never lists a token, password, or secret, and `ArchitectureTest` fails when one does. A delete returns 204.

## Paginate Inertia pages with a list and a pagination prop

The Query returns a paginator with `withQueryString()`. The page's props class holds a list prop named for the resource (`entries`), mapped from `$paginator->items()`, and a `pagination` prop: a `#[TypeScript]` class with `current_page`, `last_page`, `total`, `previous_page_url`, and `next_page_url`.
