<?php

namespace App\Http\Props;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The filters a list page was rendered with, so its toolbar shows them.
 */
#[TypeScript]
final readonly class SearchFilters
{
    public function __construct(
        public ?string $search,
    ) {}
}
