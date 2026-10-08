---
paths:
    - 'app/Http/Controllers/**'
---

# Controllers

## Centralize authorization in controllers and policies

Call `Gate::authorize()` explicitly at the beginning of every protected controller action. Policies define the actor permission requirements; controllers remain the authorization entry point.

## Keep Eloquent queries out of controllers

Controllers do not call `{Model}::query()`, build relationship queries, or use `DB`. Reads go through a Query object in `app/Queries/{Feature}` and writes through an Action. Route model binding, and `loadMissing()` on an already-resolved model to shape a response, are allowed.

## Shape Inertia props explicitly

Map models to arrays that list each field before passing them to `Inertia::render()`. Never pass a raw model as a prop.
