---
paths:
    - 'resources/js/**'
---

# Js

## Drive frontend access control from auth permissions

Use `auth.permissions` for navigation and control visibility; never infer access from role names. Treat frontend checks as interface behavior only, with backend policies remaining authoritative.

## Use the shared Auth permission contract

The shared `Auth` contract exposes the authenticated user and direct plus role-inherited permission names in `permissions: string[]`. Check the exact permission names defined by the backend system-permission vocabulary.
