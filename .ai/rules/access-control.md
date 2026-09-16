---
paths:
    - 'app/Actions/AccessControl/**'
---

# Access Control

## Enforce access-control invariants in Actions

Actions enforce protected-record, required-assignment, and in-use deletion invariants independently of actor authorization. Use the fixed `web` guard for access-control roles and permissions.
