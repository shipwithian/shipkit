<?php

namespace App\Domain\Shared\Eloquent;

use Illuminate\Database\Eloquent\Builder;

/**
 * Refuse query-builder bulk writes on an aggregate with invariant guards.
 *
 * Use on every model that registers `updating` or `deleting` guards in `booting()`.
 */
trait GuardsBulkWrites
{
    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return GuardedBuilder<*>
     */
    public function newEloquentBuilder($query): GuardedBuilder
    {
        return new GuardedBuilder($query);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query): Builder
    {
        parent::setKeysForSaveQuery($query);

        if ($query instanceof GuardedBuilder) {
            $query->forSingleRecordWrite();
        }

        return $query;
    }
}
