<?php

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Routing\Route;
use ShipKit\Core\Architecture\AudienceNaming;

uses(TestCase::class);

/**
 * A route as the application would register it, pointing at an application controller.
 */
function appRoute(string $uri, ?string $name = null, string $controller = 'App\\Http\\Controllers\\Orders\\OrderController@index'): Route
{
    $route = new Route(['GET'], $uri, ['uses' => $controller, 'controller' => $controller]);

    return $name === null ? $route : $route->name($name);
}

it('names the application\'s routes and pages after features, never the role that uses them', function () {
    $violations = (new AudienceNaming)->violations(
        app('router')->getRoutes()->getRoutes(),
        array_map(basename(...), glob(resource_path('js/pages/*'), GLOB_ONLYDIR) ?: []),
    );

    expect($violations)->toBeEmpty(implode(PHP_EOL, $violations));
});

it('fails on a role in a route\'s path or name', function (Route $route, string $violation) {
    expect((new AudienceNaming)->violations([$route], []))->toBe([$violation]);
})->with([
    'a path prefix' => [appRoute('staff/orders', 'orders.index'), 'staff/orders: the path names an audience ("staff"). Name it after the resource, such as /orders, and let the policy decide who may open it.'],
    'a nested segment' => [appRoute('kitchen/admin/queue', 'kitchen.queue'), 'kitchen/admin/queue: the path names an audience ("admin"). Name it after the resource, such as /kitchen/queue, and let the policy decide who may open it.'],
    'a route name' => [appRoute('orders', 'staff.orders.index'), 'orders: the route name staff.orders.index names an audience ("staff"). Name it after the resource, such as orders.index.'],
    'a system role' => [appRoute('super-admin/settings', 'settings.edit'), 'super-admin/settings: the path names an audience ("super-admin"). Name it after the resource, such as /settings, and let the policy decide who may open it.'],
]);

it('fails on a page folder named for a role', function () {
    expect((new AudienceNaming)->violations([], ['orders', 'admin']))
        ->toBe(['resources/js/pages/admin: the page folder names an audience. Put pages in the folder of their feature, such as pages/orders, and show their links by ability.']);
});

it('passes a resource whose name only contains a role word, and routes outside the application', function () {
    $routes = [
        appRoute('orders/{order}/manage', 'orders.manage'),
        appRoute('administration-fees', 'administration-fees.index'),
        appRoute('admin', 'filament.admin', 'Filament\\Http\\Controllers\\DashboardController@index'),
    ];

    expect((new AudienceNaming)->violations($routes, []))->toBe([]);
});

it('checks Inertia routes that render a page directly', function () {
    $route = appRoute('staff/menu', 'menu.edit', 'Inertia\\Controller');

    expect((new AudienceNaming)->violations([$route], []))->toHaveCount(1);
});

it('adds a project\'s own role names', function () {
    config(['shipkit.architecture.audience_words' => ['barista']]);

    expect((new AudienceNaming)->violations([appRoute('barista/queue', 'queue.index')], []))->toHaveCount(1);
});

it('skips a path recorded as a deliberate exception', function () {
    config(['shipkit.architecture.audience_exceptions' => ['staff' => 'an HR app: staff members are the resource it manages']]);

    expect((new AudienceNaming)->violations([appRoute('staff/{member}', 'staff.show')], ['staff']))->toBe([]);
});
