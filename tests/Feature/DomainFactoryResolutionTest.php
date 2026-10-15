<?php

use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

it('resolves a context factory to its domain model without a $model property', function () {
    if (! class_exists('Database\Factories\Domain\AccessControl\Models\RoleFactory')) {
        eval('namespace Database\Factories\Domain\AccessControl\Models; class RoleFactory extends \Illuminate\Database\Eloquent\Factories\Factory { public function definition(): array { return []; } }');
    }

    $factoryClass = 'Database\Factories\Domain\AccessControl\Models\RoleFactory';

    expect((new $factoryClass)->modelName())->toBe(Role::class);
});

it('keeps resolving factories for models in app/Models', function () {
    expect((new UserFactory)->modelName())->toBe(User::class);
});

it('resolves every factory to an existing model', function () {
    $unresolved = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(database_path('factories'), FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! str_ends_with($file->getPathname(), '.php')) {
            continue;
        }

        $class = 'Database\\Factories\\'.str_replace('/', '\\', Str::beforeLast(Str::after($file->getPathname(), database_path('factories').'/'), '.php'));

        try {
            $model = (new $class)->modelName();

            if (! is_subclass_of($model, Model::class)) {
                $unresolved[] = "{$class} resolves to [{$model}], which is not a model";
            }
        } catch (Throwable $exception) {
            $unresolved[] = "{$class}: {$exception->getMessage()}";
        }
    }

    expect($unresolved)->toBeEmpty(implode(PHP_EOL, $unresolved));
});
