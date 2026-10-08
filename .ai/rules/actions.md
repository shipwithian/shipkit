---
paths:
    - 'app/Actions/**'
---

# Actions

## Represent one named use case

Each Action is one workflow named `{Verb}{Model}Action` in `app/Actions/{Feature}/`, with a single `handle()` method. Do not create catch-all classes with unrelated methods. Classes whose names and contracts are dictated by a package, such as `app/Actions/Fortify`, keep the package's conventions.

## Stay independent of HTTP

Accept the specific typed values and trusted models the operation needs; never a Form Request. Never return a redirect, JSON, or other HTTP response.

## Enforce invariants independently of authorization

Check protected-record, ownership, in-use, and similar business rules inside the Action so console commands, jobs, and other non-HTTP callers cannot bypass them. Policies decide whether the actor may attempt the operation; the Action decides whether it is valid.

## Own the transaction for multi-step writes

Wrap writes that must commit or roll back together in one transaction inside the Action. Dispatch events, notifications, and other side effects only after it succeeds.
