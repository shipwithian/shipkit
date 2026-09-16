---
paths:
    - 'app/Http/Requests/**'
---

# Requests

## Keep Form Requests validation-only

Form Requests own validation and normalization only. Do not add actor authorization methods; controllers invoke the applicable policy explicitly.
