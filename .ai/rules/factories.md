---
paths:
    - 'database/factories/**'
---

# Factories

## Mirror the model's namespace

A factory for `App\Domain\{Context}\Models\{Model}` lives at `database/factories/Domain/{Context}/Models/{Model}Factory.php` in the namespace `Database\Factories\Domain\{Context}\Models`. A factory for `App\Models\{Model}` lives at `database/factories/{Model}Factory.php`. `AppServiceProvider` maps a factory to its model by this mirroring, so no `$model` property is needed.

Generate both together with `php artisan make:model 'App\Domain\{Context}\Models\{Model}' -mf --no-interaction`. `tests/Feature/DomainFactoryResolutionTest.php` fails when a factory does not resolve to an existing model.
