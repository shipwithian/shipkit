# Laravel Active Record + Actions Architecture

Use Laravel's normal application structure and Eloquent's Active Record pattern. An Eloquent model is the persisted record: it owns table mapping, relationships, casts, scopes, and small behavior that changes its own state. Actions are a thin workflow layer above those models; controllers coordinate HTTP.

This architecture deliberately uses Laravel and Eloquent directly. Actions do not replace Eloquent models, persistence, or the domain with a separate layer. Do not introduce repositories, record mappers, separate domain entities, or ports merely to add layers.

```text
Form Request → Controller → Action → Eloquent Active Record → Database
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

## Structure

Organize shared Laravel concerns at the top level. Group Actions, Form Requests, and feature tests by feature when a concern has several related classes. Keep controllers, resources, models, policies, and other concerns flat until they have a clear need for further organization.

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
│   │   └── {Model}Controller.php
│   ├── Requests/
│   │   └── {Feature}/
│   │       ├── Store{Model}Request.php
│   │       └── Update{Model}Request.php
│   └── Resources/
│       └── {Model}Resource.php
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

tests/
├── Feature/
│   └── {Feature}/
│       ├── Create{Model}Test.php
│       ├── Update{Model}Test.php
│       └── List{Model}Test.php
└── Unit/
   ├── Actions/
   └── Services/
```

Create services, events, exceptions, resources, policies, and query objects only when they have a clear responsibility.

Use a feature folder only when it groups several related files. For example, several requests for one feature belong in `Http/Requests/{Feature}/`; keep a lone controller or resource in its conventional folder rather than adding a directory solely for it.

## Actions

An Action represents one named use case. It is an application-workflow layer over Active Record, not a second model or domain layer.

Use names that state intent:

- `Create{Model}Action`
- `Update{Model}Action`
- `Delete{Model}Action`
- `Publish{Model}Action`

Avoid generic manager services such as `{Model}Service` with unrelated `create`, `update`, `delete`, and `publish` methods. Those operations should normally be separate Actions.

An Action accepts the specific typed values its operation needs. Actions may query Eloquent, create or mutate models, invoke model behavior, coordinate several models, use transactions, call focused services, dispatch events, and call external-system abstractions. They may return an Eloquent model, collection, scalar, or result object.

The following is pseudocode: replace placeholders and types with concrete PHP names before using it.

```text
final class Create{Model}Action
{
   handle({Actor} actor, mixed value): {Model}
   {
       return actor.{Relation}()->create({ {Field}: value });
   }
}
```

Using Laravel and Eloquent inside an Action is intentional. The Action orchestrates; the Eloquent model persists and owns its local state. Keep a controller from becoming an unstructured application layer, but do not add an Action solely for the sake of layering.

## Controllers and Form Requests

Controllers coordinate HTTP. A controller should normally:

1. Receive the request and obtain the authenticated actor.
2. Authorize the operation.
3. Pass only the validated, typed values the Action needs.
4. Invoke one Action.
5. Return the HTTP, Inertia, or resource response.

The following is pseudocode:

```text
{Model}Controller.store(
   Store{Model}Request request,
   Create{Model}Action create{Model},
): RedirectResponse
{
   actor = request.authenticatedActor();

   authorize create {Model};

   create{Model}.handle(actor, request.validated('{Field}'));

   return redirect to {Feature}.index;
}
```

Form Requests own HTTP validation and normalization. Do not pass a Form Request into an Action; pass only the validated values it needs, with trusted Eloquent models supplied separately. Request input must not decide trusted ownership fields, such as a foreign key, when the authenticated actor determines ownership.

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

## Services and Queries

Services are supporting capabilities, not the center of the architecture. Use one when logic is shared across Actions, represents a named calculation, wraps an external capability, or would otherwise obscure an Action.

Use focused names such as `{Model}CalculationService`, `{Model}IntegrationService`, or `{Model}PricingService`. Avoid catch-all services that duplicate a feature's Actions.

Actions may query Eloquent directly for ordinary reads. Introduce a Query object only when query construction becomes large or reusable.

The following is pseudocode:

```text
class List{Model}Action
{
   handle({Actor} actor): Collection<{Model}>
   {
       return actor.{Relation}().latest().get();
   }
}
```

## Resources, Policies, Events, and Exceptions

- **Resources** control the client-facing JSON or Inertia data shape. They do not query the database or perform application workflows.
- **Policies** answer authorization questions at the delivery boundary. Scope sensitive record lookups to the authenticated actor or tenant before mutation or disclosure.
- **Events** represent meaningful completed facts, such as `{Model}Created` or `{Model}Updated`. An Action performs the primary operation; listeners may handle secondary reactions.
- **Exceptions** communicate meaningful failures, such as `{Model}CannotBeUpdated`. Do not create custom exceptions for ordinary validation failures.

Do not hide essential workflow in Eloquent observers. Keep critical behavior explicit in the Action.

## Request Flows

### Write operations

```text
HTTP Request
   ↓
Form Request validation
   ↓
Controller
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
Controller
   ↓
Action or Query object
   ↓
Eloquent query
   ↓
Model or collection
   ↓
Resource or Inertia props
```

## Dependency Guidelines

```text
HTTP
   ↓
Actions
   ↓
Models / Services / Laravel
```

| Component | May depend on |
| --- | --- |
| Controller | Requests, policies, Actions, resources, Laravel HTTP |
| Action | Eloquent models, services, events, exceptions, Laravel facilities |
| Service | Models, external SDK abstractions, Laravel facilities when appropriate |
| Model | Eloquent, casts, relations, enums, small model-local behavior |
| Resource | Models and Laravel resource APIs |
| Policy | Models and the authenticated actor |

The key restriction is simple: controllers should not become the application layer. Actions own workflows; Eloquent models remain the Active Record layer.

## Testing

Use Laravel feature tests as the primary confidence layer. Organize feature tests by feature and observable operation.

Feature tests should cover authentication, authorization, validation, database state, relationships, response shape, important events, and cross-actor or tenant isolation.

Test Actions directly when it improves confidence. Actions that rely on Eloquent may use Laravel's database test support; do not introduce fake repositories merely to label the test a unit test. Keep fast unit tests for pure calculations and similar framework-independent logic.

## Review Checklist

- [ ] Each Action describes one clear use case.
- [ ] Controllers coordinate HTTP rather than own application workflows.
- [ ] Form Requests validate and normalize input before an Action runs.
- [ ] Actions receive only the explicit typed values they need.
- [ ] Eloquent models remain the Active Record layer, owning persistence configuration and small local behavior.
- [ ] Services and query objects have a focused, reusable responsibility.
- [ ] Resources expose only intentional client-facing data.
- [ ] Ownership and authorization boundaries are enforced.
- [ ] Feature tests prove observable behavior and persisted state.
- [ ] Supporting classes exist because they clarify a real responsibility.
