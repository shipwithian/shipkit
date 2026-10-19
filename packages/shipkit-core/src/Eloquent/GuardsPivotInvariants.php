<?php

namespace ShipKit\Core\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Guard invariants that depend on a relationship, such as "a protected role keeps its protected
 * permissions", by attaching through a guarded pivot model.
 *
 * Declare the relation with `->using(TheGuardedPivot::class)` on both sides. Laravel then detaches
 * through the pivot one row at a time, so `detach()`, `sync()`, and package helpers such as
 * `removeRole()` all run {@see guardDelete()}. A bulk `detach()` that never reaches the pivot model
 * would skip the guard, so a guarded relation must always declare its pivot.
 *
 * Use on a class that extends `Pivot` or `MorphPivot`, in place of {@see GuardsInvariants}.
 */
trait GuardsPivotInvariants
{
    use GuardsInvariants;

    /**
     * A pivot deletes through `Pivot::delete()`, which returns the number of rows deleted. Running
     * it in a transaction keeps any `lockForUpdate()` the guard took held until the delete commits.
     *
     * @return int
     */
    public function delete()
    {
        return DB::transaction(fn () => $this->runGuardedWrite('delete', fn () => parent::delete()));
    }

    /**
     * Pivot rows without a primary key delete through a bare query that fires `deleting` but never
     * reaches `performDeleteOnModel()`, so the guard also runs here when model events are silenced.
     *
     * @return Builder<static>
     */
    protected function getDeleteQuery()
    {
        $query = parent::getDeleteQuery();

        if ($this->guardedWrite === 'delete') {
            if (self::modelEventsAreSilenced()) {
                $this->guardDelete();
            }

            $this->openSingleRecordWrite($query);
        }

        return $query;
    }
}
