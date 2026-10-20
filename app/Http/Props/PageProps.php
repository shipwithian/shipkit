<?php

namespace App\Http\Props;

use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

/**
 * The props one Inertia page receives. Each public property is one prop, and `composer types:generate`
 * turns the class into the page's TypeScript type, so PHP is the only place a payload is declared.
 */
abstract readonly class PageProps implements ProvidesInertiaProperties
{
    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return get_object_vars($this);
    }
}
