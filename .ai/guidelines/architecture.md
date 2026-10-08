# Laravel Active Record + Actions Architecture

Use Laravel's normal application structure and Eloquent's Active Record pattern. An Eloquent model is the persisted record: it owns table mapping, relationships, casts, scopes, and small behavior that changes its own state. Actions are a thin workflow layer above those models; Query objects own reads; controllers coordinate HTTP.

This architecture deliberately uses Laravel and Eloquent directly. Actions do not replace Eloquent models, persistence, or the domain with a separate layer. Do not introduce repositories, record mappers, separate domain entities, or ports merely to add layers.

```text
Write: Form Request → Controller → Action → Eloquent Active Record → Database
Read:  Controller → Query object → Eloquent Active Record → Database
```

## Template Placeholders

The examples in this guide are templates, not application classes:

- `{Feature}` is a plural feature folder name.
- `{Model}` is a singular Eloquent model name.
- `{Action}` is a verb that describes one operation.
- `{Actor}` is the authenticated model or another trusted caller that initiates the operation.
- `{model}` is an instance of `{Model}`.
- `{Relation}` is the Eloquent relationship through which the actor owns or creates records.
- `{Field}` is a validated input field required by the operation.

For example, `{Action}{Model}Action` describes a named use-case class. Replace every placeholder with names from the feature being built.

Use an Action when an operation is a named workflow: it creates a record, changes state, coordinates multiple models or systems, requires a transaction, or has behavior worth testing independently. For small model-local changes, keep the behavior on the Eloquent model.

Use a Query object for every read that builds an Eloquent query for a page, list, or API response. Controllers never query the database themselves.

## Structure

Organize shared Laravel concerns at the top level. Group Actions, Queries, Form Requests, and feature tests by feature when a concern has several related classes. Group versioned JSON API controllers, requests, and resources under `Api/V{n}`. Keep other controllers, models, policies, and concerns flat until they have a clear need for further organization.

```text
app/
├── Actions/
│   └── {Feature}/
│       ├── Create{Model}Action.php
│       ├── Update{Model}Action.php
│       └── Delete{Model}Action.php
├── Enums/
│   └── {Model}Status.php
├── Events/
│   └── {Model}Created.php
├── Exceptions/
│   └── {Model}CannotBeUpdated.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/{Model}Controller.php
│   │   └── {Model}Controller.php
│   ├── Requests/
│   │   ├── Api/V1/Store{Model}Request.php
│   │   └── {Feature}/
│   │       ├── Store{Model}Request.php
│   │       └── Update{Model}Request.php
│   └── Resources/
│       └── Api/V1/{Model}Resource.php
├── Models/
│   └── {Model}.php
├── Policies/
│   └── {Model}Policy.php
├── Queries/
│   └── {Feature}/
│       └── {Model}ListQuery.php
└── Services/
   └── {Feature}/
       └── {Model}CalculationService.php

resources/js/
├── pages/
│   └── {feature}/
│       ├── index.tsx
│       └── partials/
│           ├── {models}-table.tsx
│           ├── create-{model}-dialog.tsx
│           ├── edit-{model}-dialog.tsx
│           └── delete-{model}-dialog.tsx
└── types/
   └── {feature}.ts

tests/
├── Feature/
│   └── {Feature}/
│       ├── {Model}ManagementTest.php
│       ├── {Model}PolicyTest.php
│       └── {Verb}{Model}ActionTest.php
└── Unit/
   └── Services/
```

Actions and Query objects are the standard homes for writes and reads. Create services, events, exceptions, resources, and policies only when they have a clear responsibility.

Use a feature folder only when it groups several related files. For example, several requests for one feature belong in `Http/Requests/{Feature}/`; keep a lone controller or resource in its conventional folder rather than adding a directory solely for it.

## Actions

An Action represents one named use case. It is an application-workflow layer over Active Record, not a second model or domain layer.

Use names that state intent:

- `Create{Model}Action`
- `Update{Model}Action`
- `Delete{Model}Action`
- `Publish{Model}Action`

Avoid generic manager services such as `{Model}Service` with unrelated `create`, `update`, `delete`, and `publish` methods. Those operations should normally be separate Actions.

Classes whose names and contracts are dictated by a package, such as the Fortify actions under `app/Actions/Fortify`, keep the package's conventions.

An Action accepts the specific typed values its operation needs. Actions may query Eloquent, create or mutate models, invoke model behavior, coordinate several models, use transactions, call focused services, dispatch events, and call external-system abstractions. They may return an Eloquent model, collection, scalar, or result object.

The following is pseudocode: replace placeholders and types with concrete PHP names before using it.

```text
class Create{Model}Action
{
   handle({Actor} actor, mixed value): {Model}
   {
       return actor.{Relation}()->create({ {Field}: value });
   }
}
```

Using Laravel and Eloquent inside an Action is intentional. The Action orchestrates; the Eloquent model persists and owns its local state. Keep a controller from becoming an unstructured application layer, but do not add an Action solely for the sake of layering.

### Invariants

Actions enforce business invariants independently of actor authorization: protected records, required assignments, in-use deletion guards, ownership, and similar rules that must hold for every caller. A policy decides whether this actor may attempt the operation; the Action decides whether the operation is valid at all. Console commands, jobs, and other non-HTTP callers must not be able to bypass an invariant by skipping the controller.

### Transactions, errors, and side effects

- An Action owns the transaction boundary for a multi-step write that must commit or roll back as one operation.
- Dispatch events and other side effects only after the primary state change succeeds. Use queued work when a side effect needs retries or durability.
- Report business failures through validation exceptions or focused exceptions such as `{Model}CannotBeUpdated`.
- Actions never accept a Form Request and never return an HTTP response.

## Controllers and Form Requests

Controllers coordinate HTTP. A controller should normally:

1. Call `Gate::authorize()` first when the action is protected.
2. Receive the request and obtain the authenticated actor.
3. Pass only the validated, typed values the Action or Query object needs.
4. Invoke one Action for a write, or one Query object for a read.
5. Return the HTTP, Inertia, or resource response.

Controllers do not call `{Model}::query()`, build relationship queries, or use `DB`. Route model binding, and `loadMissing()` on an already-resolved model to shape a response, are allowed.

The following is pseudocode:

```text
{Model}Controller.store(
   Store{Model}Request request,
   Create{Model}Action create{Model},
): RedirectResponse
{
   Gate::authorize('create', {Model}::class);

   actor = request.authenticatedActor();

   create{Model}.handle(actor, request.validated('{Field}'));

   return redirect to {Feature}.index;
}
```

Form Requests own HTTP validation and normalization only. They do not authorize the actor; do not add `authorize()` logic to them. Do not pass a Form Request into an Action; pass only the validated values it needs, with trusted Eloquent models supplied separately. Request input must not decide trusted ownership fields, such as a foreign key, when the authenticated actor determines ownership.

## Authorization

Authorization happens at the HTTP delivery boundary:

- Routes apply authentication, email verification, throttling, and signed-URL middleware. They do not apply permission middleware.
- Every protected controller action calls `Gate::authorize()` explicitly as its first statement.
- Policies answer whether the actor may perform the operation.
- Form Requests validate only.
- Actions still enforce invariants, and Query objects still scope records to the trusted actor or tenant, so no caller can bypass them.

Route model binding constrains lookup; it does not replace authorization. A valid request is not necessarily authorized, and an authenticated actor is not automatically allowed to access every record.

## Eloquent Models: the Active Record Layer

Eloquent models are the Active Record and persistence layer. They own table configuration, relationships, casts, scopes, and small model-local behavior. They may create, update, and delete their own records through normal Eloquent APIs.

The following is pseudocode:

```text
class {Model} extends Model
{
   casts: { status: {Model}Status }

   applyStateChange(): void
   {
       update({ status: {Model}Status.Completed });
   }
}
```

Prefer meaningful model behavior such as `{model}.applyStateChange()` over repeated raw state mutation. Keep workflows that coordinate multiple models or systems in an Action or focused Service. Do not move a simple model-local change into an Action just to create another class.

## Services

Services are supporting capabilities, not the center of the architecture. Use one when logic is shared across Actions, represents a named calculation, wraps an external capability, or would otherwise obscure an Action.

Use focused names such as `{Model}CalculationService`, `{Model}IntegrationService`, or `{Model}PricingService`. Avoid catch-all services that duplicate a feature's Actions.

## Queries

A Query object owns one read: the Eloquent query behind a page, list, or API response. It lives in `app/Queries/{Feature}/`, is named for what it returns, such as `{Model}ListQuery`, and exposes a single `handle()` method.

A Query object:

- accepts the trusted actor, parent model, and typed filters it needs, never a Form Request;
- applies ownership, tenant, filtering, and ordering constraints;
- eager loads the relationships and counts the response needs;
- returns models, a collection, or a paginator, not arrays shaped for the client;
- does not write to the database.

The following is pseudocode:

```text
class {Model}ListQuery
{
   handle({Actor} actor): Collection<{Model}>
   {
       return actor.{Relation}().latest().get();
   }
}
```

An Action may run the queries its own write needs, such as looking up a record it is about to change. Reuse a Query object from an Action only when both need the same read.

## Response Shaping

- **JSON API endpoints** return an API Resource under `Http/Resources/Api/V{n}`, or a purpose-built `JsonResponse` when no model is returned.
- **Inertia pages** may receive models mapped to explicit arrays in the controller. List every exposed field; never pass a raw model or `toArray()` as a prop.

Resources and controller mapping transform already-loaded data. They do not query the database, trigger lazy loading, or perform application workflows. Do not expose passwords, tokens, secrets, or internal state.

## Policies, Events, and Exceptions

- **Policies** answer authorization questions at the delivery boundary. Scope sensitive record lookups to the authenticated actor or tenant before mutation or disclosure.
- **Events** represent meaningful completed facts, such as `{Model}Created` or `{Model}Updated`. An Action performs the primary operation; listeners may handle secondary reactions.
- **Exceptions** communicate meaningful failures, such as `{Model}CannotBeUpdated`. Do not create custom exceptions for ordinary validation failures.

Do not hide essential workflow in Eloquent observers. Keep critical behavior explicit in the Action.

## Frontend Pages

Inertia pages mirror the controller that renders them:

- `pages/{feature}/{action}.tsx` is a routable page named after the controller action (`index`, `create`, `edit`, `show`). It reads props and composes partials.
- `pages/{feature}/partials/` holds the feature's pieces, named for the action they submit to: `create-{model}-dialog.tsx` (store), `edit-{model}-dialog.tsx` (update), `delete-{model}-dialog.tsx` (destroy), and `{models}-table.tsx` (index list).
- Partials use named exports and are never rendered by name from a controller. A partial moves to `components/` only when a second feature uses it.
- Types for server-provided props live in `resources/js/types/{feature}.ts`.

Frontend permission checks only control what is shown; policies remain authoritative.

## Request Flows

### Write operations

```text
HTTP Request
   ↓
Form Request validation
   ↓
Controller authorization
   ↓
Action
   ├── Eloquent Active Record
   ├── Service
   ├── Transaction
   └── Event
   ↓
Database
   ↓
Response
```

### Read operations

```text
HTTP Request
   ↓
Controller authorization
   ↓
Query object
   ↓
Eloquent query
   ↓
Model, collection, or paginator
   ↓
Resource or Inertia props
```

## Dependency Guidelines

```text
HTTP
   ↓
Actions / Queries
   ↓
Models / Services / Laravel
```

| Component  | May depend on                                                              |
| ---------- | -------------------------------------------------------------------------- |
| Controller | Requests, policies, Actions, Queries, resources, Laravel HTTP              |
| Action     | Eloquent models, Queries, services, events, exceptions, Laravel facilities |
| Query      | Eloquent models, relationships, scopes, and the query builder              |
| Service    | Models, external SDK abstractions, Laravel facilities when appropriate     |
| Model      | Eloquent, casts, relations, enums, small model-local behavior              |
| Resource   | Models and Laravel resource APIs                                           |
| Policy     | Models and the authenticated actor                                         |

The key restriction is simple: controllers should not become the application layer. Actions own workflows; Query objects own reads; Eloquent models remain the Active Record layer.

## Testing

Use Laravel feature tests as the primary confidence layer. Organize them by feature in `tests/Feature/{Feature}/`, with no by-type folders: policy, Action, and Query tests sit beside the feature's HTTP tests.

Use one file per resource, such as `{Model}ManagementTest`, with a `describe()` block per controller action and one behaviour per `it()` case. When a file would cover several unrelated endpoints, split it into one file per flow. Within each action, order cases as guest, forbidden, validation, success, then invariants.

Feature tests should cover authentication, authorization, validation, database state, relationships, response shape, important events, and cross-actor or tenant isolation.

Test Actions and Query objects directly when it improves confidence, for example an invariant with several branches or a query with non-trivial filtering. Actions and Query objects that rely on Eloquent may use Laravel's database test support; do not introduce fake repositories merely to label the test a unit test. Keep fast unit tests for pure calculations and similar framework-independent logic.

## Review Checklist

- [ ] Each Action describes one clear use case.
- [ ] Controllers coordinate HTTP rather than own application workflows.
- [ ] Every protected controller action calls `Gate::authorize()` first.
- [ ] Controllers contain no Eloquent queries; each read goes through a Query object.
- [ ] Routes carry no permission middleware.
- [ ] Form Requests validate and normalize input only, with no authorization logic.
- [ ] Actions receive only the explicit typed values they need.
- [ ] Actions enforce invariants independently of actor authorization.
- [ ] Multi-step writes run in one transaction, and side effects fire after it succeeds.
- [ ] Eloquent models remain the Active Record layer, owning persistence configuration and small local behavior.
- [ ] Services have a focused, reusable responsibility.
- [ ] API Resources and Inertia props expose only intentional, explicitly listed data.
- [ ] Ownership and authorization boundaries are enforced.
- [ ] Feature tests prove observable behavior and persisted state, grouped by feature with a `describe()` block per action.
- [ ] Pages compose partials named for their controller action; shared prop types live in `types/{feature}.ts`.
- [ ] Supporting classes exist because they clarify a real responsibility.
