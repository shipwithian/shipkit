<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A list page's search box, for Query objects: rows where any of the columns contains the text,
 * ignoring case, with `%` and `_` matched literally. It escapes with `!`, which SQLite, MySQL, and
 * Postgres all accept, and lowers both sides, because Postgres compares case-sensitively.
 */
trait SearchesText
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<literal-string>  $columns  column names written in the Query, never input
     * @return Builder<TModel>
     */
    protected function whereContains(Builder $query, array $columns, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';

        return $query->where(function (Builder $matches) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $matches->orWhereRaw('lower('.$column.") like ? escape '!'", [$pattern]);
            }
        });
    }
}
