---
paths:
    - 'app/Http/Controllers/**'
---

# Controllers

## Centralize authorization in controllers and policies

Call `Gate::authorize()` explicitly at the beginning of every protected controller action. Reading the actor (`$user = $request->user();`) may come first; nothing else may. `tests/Feature/RouteAuthorizationTest.php` checks every action behind `auth` middleware. An authenticated action that genuinely needs no authorization goes in its `ACTIONS_WITHOUT_GATE` list with the reason. Policies define the actor permission requirements; controllers remain the authorization entry point.

## Keep Eloquent queries out of controllers

Controllers do not call `{Model}::query()`, build relationship queries, or use `DB`. Reads go through a Query object in `app/Queries/{Feature}` and writes through an Action in `app/Actions/{Feature}`. Do not catch `DomainException`s; they render as validation errors. Route model binding, and `loadMissing()` on an already-resolved model to shape a response, are allowed.

## Group controllers by feature

Place web controllers in `app/Http/Controllers/{Feature}/`, named exactly after a feature folder in `app/Actions` or `app/Queries`, and versioned API controllers in `app/Http/Controllers/Api/V{n}/`. `ArchitectureTest` rejects any other folder.

## Shape Inertia props explicitly

Pass `Inertia::render()` a typed props object, `new {Page}Props(...)`, whose item classes map each model field explicitly; `responses.md` describes the classes. Never pass an array or a raw model as props.
