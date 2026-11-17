<?php

namespace ShipKit\Core\Architecture;

use Illuminate\Routing\Route;
use ShipKit\Core\AccessControl\SystemRole;

/**
 * Finds application routes and page folders named after an audience (`/staff/orders`,
 * `admin.users.index`, `pages/admin`) instead of their feature and resource. A role prefix protects
 * nothing: authentication, the policy each action authorizes with, and the abilities the UI uses to
 * show links do that, and a Query scoped to the actor decides what each one sees.
 */
class AudienceNaming
{
    /**
     * Words that name who uses a page rather than what it is. A project adds its own role names in
     * `shipkit.architecture.audience_words`.
     */
    public const array AUDIENCE_WORDS = [
        'admin', 'admins', 'administrator', 'administrators', 'superadmin', 'backoffice', 'back-office',
        'staff', 'employee', 'employees', 'manager', 'managers', 'moderator', 'moderators',
    ];

    /**
     * @param  iterable<Route>  $routes
     * @param  list<string>  $pageFolders  the folder names directly under resources/js/pages
     * @return list<string>
     */
    public function violations(iterable $routes, array $pageFolders): array
    {
        $violations = [];

        foreach ($routes as $route) {
            if (! $this->belongsToApplication($route) || $this->isException($route->uri())) {
                continue;
            }

            $path = $this->audiencesIn(explode('/', $route->uri()));

            if ($path !== []) {
                $violations[] = "{$route->uri()}: the path names an audience (\"{$path[0]}\"). Name it after the resource, such as /".$this->without(explode('/', $route->uri()), $path, '/').', and let the policy decide who may open it.';

                continue;
            }

            $name = $route->getName();
            $inName = $name === null ? [] : $this->audiencesIn(explode('.', $name));

            if ($inName !== []) {
                $violations[] = "{$route->uri()}: the route name {$name} names an audience (\"{$inName[0]}\"). Name it after the resource, such as ".$this->without(explode('.', (string) $name), $inName, '.').'.';
            }
        }

        foreach ($pageFolders as $folder) {
            if ($this->audiencesIn([$folder]) !== [] && ! $this->isException($folder)) {
                $violations[] = "resources/js/pages/{$folder}: the page folder names an audience. Put pages in the folder of their feature, such as pages/orders, and show their links by ability.";
            }
        }

        return $violations;
    }

    /**
     * Routes the application defines: its own controllers, and pages rendered straight from a route.
     */
    private function belongsToApplication(Route $route): bool
    {
        $action = $route->getActionName();

        return str_starts_with($action, 'App\\') || str_starts_with($action, 'Inertia\\Controller');
    }

    /**
     * @param  list<string>  $segments
     * @return list<string>
     */
    private function audiencesIn(array $segments): array
    {
        $words = array_map(strtolower(...), [
            ...self::AUDIENCE_WORDS,
            ...array_map(fn (SystemRole $role): string => $role->value, SystemRole::cases()),
            ...array_filter((array) config('shipkit.architecture.audience_words', []), is_string(...)),
        ]);

        return array_values(array_filter($segments, fn (string $segment): bool => in_array(strtolower($segment), $words, true)));
    }

    /**
     * @param  list<string>  $segments
     * @param  list<string>  $audiences
     */
    private function without(array $segments, array $audiences, string $glue): string
    {
        return implode($glue, array_diff($segments, $audiences));
    }

    private function isException(string $path): bool
    {
        foreach (array_keys((array) config('shipkit.architecture.audience_exceptions', [])) as $exception) {
            $exception = trim((string) $exception, '/');

            if ($path === $exception || str_starts_with($path, "{$exception}/")) {
                return true;
            }
        }

        return false;
    }
}
