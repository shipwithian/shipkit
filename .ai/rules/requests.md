---
paths:
    - 'app/Http/Requests/**'
---

# Requests

## Keep Form Requests validation-only

Form Requests own validation and normalization only. Do not add actor authorization methods; controllers invoke the applicable policy explicitly.

## Group requests by context

Place Form Requests in `app/Http/Requests/{Context}/`, named exactly after a folder in `app/Domain`, or `app/Http/Requests/Api/V{n}/` for the versioned API.
