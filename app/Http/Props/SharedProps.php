<?php

namespace App\Http\Props;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The props every Inertia page receives, shared by `HandleInertiaRequests`.
 */
#[TypeScript]
final readonly class SharedProps extends PageProps
{
    public function __construct(
        public string $name,
        public AuthProps $auth,
        public bool $sidebarOpen,
    ) {}
}
