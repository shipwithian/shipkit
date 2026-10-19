---
paths:
    - 'app/Http/Controllers/**'
---

# Controllers

## Centralize authorization in controllers and policies

Call `Gate::authorize()` explicitly at the beginning of every protected controller action. Reading the actor (`$user = $request->user();`) may come first; nothing else may. `tests/Feature/RouteAuthorizationTest.php` checks every action behind `auth` middleware. An authenticated action that genuinely needs no authorization goes in its `ACTIONS_WITHOUT_GATE` list with the reason. Policies define the actor permission requirements; controllers remain the authorization entry point.

## Keep Eloquent queries out of controllers

Controllers do not call `{Model}::query()`, build relationship queries, or use `DB`. Reads go through a context's Query object in `app/Domain/{Context}/Queries` and writes through its Action in `app/Domain/{Context}/Actions`. Do not catch `DomainException`s; they render as validation errors. Route model binding, and `loadMissing()` on an already-resolved model to shape a response, are allowed.

## Group controllers by context

Place web controllers in `app/Http/Controllers/{Context}/`, named exactly after a folder in `app/Domain`, and versioned API controllers in `app/Http/Controllers/Api/V{n}/`. `ArchitectureTest` rejects any other folder.

## Shape Inertia props explicitly

Map models to arrays that list each field before passing them to `Inertia::render()`. Never pass a raw model as a prop.
