<?php

namespace App\Http\Props;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a paginated page is in its list, sent next to the page's items.
 */
#[TypeScript]
final readonly class Pagination
{
    public function __construct(
        public int $current_page,
        public int $last_page,
        public int $total,
        public ?string $previous_page_url,
        public ?string $next_page_url,
    ) {}

    /**
     * @param  LengthAwarePaginator<array-key, mixed>  $paginator
     */
    public static function from(LengthAwarePaginator $paginator): self
    {
        return new self(
            current_page: $paginator->currentPage(),
            last_page: $paginator->lastPage(),
            total: $paginator->total(),
            previous_page_url: $paginator->previousPageUrl(),
            next_page_url: $paginator->nextPageUrl(),
        );
    }
}
