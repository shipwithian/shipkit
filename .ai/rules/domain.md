---
paths:
    - 'app/Domain/**'
---

# Domain

## Put code in the context whose language it uses

Business code lives in `app/Domain/{Context}`. A context's Models, Enums, Policies, and Services are internal. Its public surface is its Actions, Query objects, domain exceptions, and events: other contexts may call those, and catch or declare its exceptions. The domain never depends on `app/Http`. `tests/Unit/ArchitectureTest.php` enforces this, and also fails when an Action, Query, or domain exception is missing from its context rule file.

## Raise broken business rules as named domain exceptions

Every invariant failure throws a named exception extending `App\Domain\Shared\Exceptions\DomainException` that returns the `field()` it is reported against. `bootstrap/app.php` renders it as a validation error. Do not throw `ValidationException` from the domain.

## Keep Eloquent as the aggregate

Do not add separate domain entities, repositories, record mappers, or ports. Protect an aggregate's own state with `ensure...()` methods on its Eloquent model, called from its `guardUpdate()` and `guardDelete()` overrides, and add `use App\Domain\Shared\Eloquent\GuardsInvariants;`. The trait runs the guards on every save and delete, including with model events silenced, and makes query-builder bulk writes throw `BulkWriteBypassesInvariants`. Register guards only through these methods, never with `static::updating()` or `static::deleting()`. To change many guarded records, load them and save or delete each one. Write guarded tables only through their models, never with `DB::table()`, and give foreign keys into them `restrictOnDelete()` so a database cascade cannot skip the guards.

## Keep the context rule file current

Each context has `.ai/rules/contexts/{context}.md` listing its vocabulary, invariants, and public surface. Update it in the same change that adds a term, an invariant, or a public class.

## Keep the shared kernel context-free

`app/Domain/Shared` holds only what every context needs, such as `DomainException` and the guarded Eloquent builder. It never depends on a context or on `app/Http`, and it is not a context.
