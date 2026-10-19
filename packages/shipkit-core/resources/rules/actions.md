---
paths:
    - 'app/Domain/*/Actions/**'
    - 'app/Actions/**'
---

# Actions

## Represent one named use case

Each Action is one workflow named `{Verb}{Model}Action` in `app/Domain/{Context}/Actions/`, with a single `handle()` method. Do not create catch-all classes with unrelated methods. Classes whose names and contracts are dictated by a package, such as `app/Actions/Fortify`, keep the package's conventions.

## Receive the acting user as a parameter

An Action or Query that needs to know who is acting takes the user as an explicit first parameter named `$actor`, and the controller passes `$request->user()`. Domain code never reads `auth()`, `Auth::user()`, `request()`, or `session()`, so a console command, job, or test supplies the actor the same way a controller does.

## Stay independent of HTTP

Accept the specific typed values and trusted models the operation needs; never a Form Request. Never return a redirect, JSON, or other HTTP response.

## Enforce invariants independently of authorization

Check protected-record, ownership, in-use, and similar business rules before the change so console commands, jobs, and other non-HTTP callers cannot bypass them. A rule about one aggregate's own state is an `ensure...()` method on the model, run by its `GuardsInvariants` guards so the Action only needs to save or delete; a rule spanning several aggregates stays in the Action. Throw a named exception extending `ShipKit\Core\Exceptions\DomainException`, never `ValidationException`. Policies decide whether the actor may attempt the operation; the invariant decides whether it is valid.

## Own the transaction for multi-step writes

Wrap writes that must commit or roll back together in one transaction inside the Action. Dispatch events, notifications, and other side effects only after it succeeds.

## Run only the reads the write needs

An Action may run the queries its own write needs, such as looking up the record it is about to change. Reuse a Query object from an Action only when the page or API response needs the same read.
