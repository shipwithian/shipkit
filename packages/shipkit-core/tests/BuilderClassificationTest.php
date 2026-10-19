<?php

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ShipKit\Core\Eloquent\BuilderMethods;
use ShipKit\Core\Eloquent\GuardedBuilder;

/**
 * Public methods of Laravel's Eloquent and query builders, as installed.
 *
 * @return list<string>
 */
function installedBuilderMethods(): array
{
    $methods = [];

    foreach ([EloquentBuilder::class, QueryBuilder::class] as $builder) {
        foreach ((new ReflectionClass($builder))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! str_starts_with($method->getName(), '__')) {
                $methods[] = $method->getName();
            }
        }
    }

    $methods = array_values(array_unique($methods));
    sort($methods);

    return $methods;
}

it('classifies every public builder method of the installed Laravel', function () {
    $classified = [...BuilderMethods::GUARDED, ...BuilderMethods::REJECTED, ...BuilderMethods::SAFE];
    $unclassified = array_diff(installedBuilderMethods(), $classified);

    expect($unclassified)->toBeEmpty(
        'Laravel added builder methods that ShipKit has not classified: '.implode(', ', $unclassified).'. '
        .'Add each to BuilderMethods as GUARDED (writes only on the guard trait\'s single-record path), '
        .'REJECTED (a write that bypasses the guards, refused by GuardedBuilder), or SAFE (reads, or writes through the model).',
    );
});

it('classifies each method once, and only methods Laravel still has', function () {
    $classified = [...BuilderMethods::GUARDED, ...BuilderMethods::REJECTED, ...BuilderMethods::SAFE];

    expect(array_diff_assoc($classified, array_unique($classified)))->toBeEmpty('A builder method is classified twice.')
        ->and(array_diff($classified, installedBuilderMethods()))->toBeEmpty('BuilderMethods lists methods the installed Laravel no longer has.');
});

it('overrides every guarded or rejected method the Eloquent builder defines', function () {
    $unhandled = [];

    foreach ([...BuilderMethods::GUARDED, ...BuilderMethods::REJECTED] as $method) {
        $definedByEloquent = method_exists(EloquentBuilder::class, $method);

        if ($definedByEloquent && (new ReflectionMethod(GuardedBuilder::class, $method))->getDeclaringClass()->getName() !== GuardedBuilder::class) {
            $unhandled[] = $method;
        }
    }

    expect($unhandled)->toBeEmpty('GuardedBuilder must override these writes, or they bypass the guards: '.implode(', ', $unhandled));
});
