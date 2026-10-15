<?php

use App\Domain\AccessControl\Models\Role;
use App\Models\User;
use Database\Factories\UserFactory;

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
