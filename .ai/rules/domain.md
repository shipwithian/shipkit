---
paths:
    - 'app/Domain/**'
---

# Domain

## Put code in the context whose language it uses

Business code lives in `app/Domain/{Context}`. A context's Models, Enums, Exceptions, and Policies are internal; other contexts use it only through its Actions, Query objects, and events. The domain never depends on `app/Http`. `tests/Unit/ArchitectureTest.php` enforces this, and also fails when an Action, Query, or domain exception is missing from its context rule file.

## Raise broken business rules as named domain exceptions

Every invariant failure throws a named exception extending `App\Domain\Shared\Exceptions\DomainException` that returns the `field()` it is reported against. `bootstrap/app.php` renders it as a validation error. Do not throw `ValidationException` from the domain.

## Keep Eloquent as the aggregate

Do not add separate domain entities, repositories, record mappers, or ports. Protect an aggregate's own state with `ensure...()` methods on its Eloquent model, registered as `updating` or `deleting` guards in the model's `booting()` so every caller hits them. Every model with such guards also uses `App\Domain\Shared\Eloquent\GuardsBulkWrites`, which makes query-builder bulk updates, deletes, and upserts throw `BulkWriteBypassesInvariants`. To change many guarded records, load them and save or delete each one. Never write guarded tables with `DB::table()`.

## Keep the context rule file current

Each context has `.ai/rules/contexts/{context}.md` listing its vocabulary, invariants, and public surface. Update it in the same change that adds a term, an invariant, or a public class.

## Keep the shared kernel context-free

`app/Domain/Shared` holds only what every context needs, such as `DomainException` and the guarded Eloquent builder. It never depends on a context or on `app/Http`, and it is not a context.
