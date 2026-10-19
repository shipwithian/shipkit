---
paths:
    - 'app/Domain/**'
    - 'packages/shipkit-core/**'
---

# Domain

## Put code in the context whose language it uses

Business code lives in `app/Domain/{Context}`. A context's Models, Enums, Policies, and Services are internal. Its public surface is its Actions, Query objects, domain exceptions, and events: other contexts may call those, and catch or declare its exceptions. The domain never depends on `app/Http`. `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php` enforces this, and also fails when an Action, Query, or domain exception is missing from its context rule file.

## Raise broken business rules as named domain exceptions

Every invariant failure throws a named exception extending `ShipKit\Core\Exceptions\DomainException` that returns the `field()` it is reported against. `bootstrap/app.php` renders it as a validation error. Do not throw `ValidationException` from the domain.

## Keep Eloquent as the aggregate

Do not add separate domain entities, repositories, record mappers, or ports. Protect an aggregate's own state with `ensure...()` methods on its Eloquent model, called from its `guardCreate()`, `guardUpdate()` and `guardDelete()` overrides, and add `use ShipKit\Core\Eloquent\GuardsInvariants;`. The trait runs the guards on every save, delete, and instance increment, including with model events silenced, and makes every query-builder write throw `BulkWriteBypassesInvariants`, even one aimed at a single row: `update`, `delete`, `insert`, `increment`, `touch`, `upsert`, `updateOrInsert`, `truncate`, `forceDelete`, and the rest listed in `ShipKit\Core\Eloquent\BuilderMethods`. Never write through `toBase()` or `getQuery()` either; a check refuses it. Register guards only through these methods, never with `static::updating()` or `static::deleting()`. To change many guarded records, load them and save or delete each one. Write guarded tables only through their models, never with `DB::table()`, and give a guarded table's foreign keys `restrictOnDelete()` (or `nullOnDelete()` when a null column cannot break the invariant) instead of cascading, because a database cascade deletes rows without running their guards. A cascade from a parent that owns the rows as parts of itself, such as a team and its memberships, is recorded in `shipkit.guarded_cascades` in `config/shipkit.php` with the reason; `vendor/shipwithian/shipkit-core/tests/GuardedTablesTest.php` fails on any other.

## Keep the context rule file current

Each context has `.ai/rules/contexts/{context}.md` listing its vocabulary, invariants, and public surface. Update it in the same change that adds a term, an invariant, or a public class.

## Guard relationships through pivots

An invariant about a relationship, such as "a protected role keeps its protected permissions", sits on a pivot model: extend `Pivot` or `MorphPivot`, add `use ShipKit\Core\Eloquent\GuardsPivotInvariants;`, implement `guardDelete()`, and declare the relation with `->using(ThePivot::class)` on both sides. Laravel then deletes pivot rows one model at a time, so `detach()`, `sync()`, and package helpers such as `removeRole()` run the guard. Sync the difference (`->sync($ids)`) rather than package helpers that detach everything first. A guard that counts what remains locks those rows with `lockForUpdate()`; the trait runs the guard and the write in one transaction.

## Keep the shared kernel in shipkit-core

The shared kernel lives in the `shipwithian/shipkit-core` package (`ShipKit\Core`, developed in `packages/shipkit-core`): `DomainException`, `BulkWriteBypassesInvariants`, the guarded Eloquent builder, and the guard traits. Projects update it with Composer, so never copy its classes into `app/`. It never depends on the application (`ShipKit\Core` must not use `App\`), and it reads the guarded `Role`, `Permission`, and `User` models from `config/shipkit.php`. `php artisan shipkit:verify`, run by `composer ci:check`, fails when its testsuite is missing from `phpunit.xml` or a mapped model does not use `GuardsInvariants`. Core also owns the access-control system records (`SystemRole`, `SystemPermission`) and `BootstrapAccessControlAction`, the only code that creates a protected record, because whoever defines a protected record must be able to evolve it with a data migration.

## Guard creation, and lock what you count

A rule about creating a record, such as "at most 50 notes per user", goes in the model's `guardCreate()`. It runs for `create()`, `save()`, `firstOrCreate()`, `createQuietly()`, and `withoutEvents()`, and it runs for a pivot row made through `attach()`. A guard that counts existing rows locks the parent row first (`User::query()->whereKey($id)->lockForUpdate()->first()`), then counts. `GuardsInvariants` runs the guard and the insert in one transaction, so two requests cannot both pass the count.
