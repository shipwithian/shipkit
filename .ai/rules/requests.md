---
paths:
    - 'app/Http/Requests/**'
---

# Requests

## Keep Form Requests validation-only

Form Requests own validation and normalization only. Do not add actor authorization methods; controllers invoke the applicable policy explicitly.

## Group requests by context

Place Form Requests in `app/Http/Requests/{Context}/`, named exactly after a folder in `app/Domain`, or `app/Http/Requests/Api/V{n}/` for the versioned API.

## Never let input decide ownership

Request input must not decide a trusted ownership field, such as a foreign key, when the authenticated actor determines ownership. The controller passes the actor, and the Action sets the owner from it.
