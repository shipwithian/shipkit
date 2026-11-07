---
paths:
    - 'database/factories/**'
---

# Factories

## Follow Laravel's naming

A factory for `App\Models\{Model}` lives at `database/factories/{Model}Factory.php`, where Laravel resolves it without a `$model` property.

Generate both together with `php artisan make:model {Model} -mf --no-interaction`.
