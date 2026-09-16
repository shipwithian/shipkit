---
paths:
    - 'app/Http/Controllers/**'
---

# Controllers

## Centralize authorization in controllers and policies

Call `Gate::authorize()` explicitly at the beginning of every protected controller action. Policies define the actor permission requirements; controllers remain the authorization entry point.
