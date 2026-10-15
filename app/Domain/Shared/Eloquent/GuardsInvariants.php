<?php

namespace App\Domain\Shared\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Events\NullDispatcher;

/**
 * Run an aggregate's invariant guards on every write, with no way around them.
 *
 * The model overrides {@see guardUpdate()} and {@see guardDelete()} to call its `ensure...()` methods.
 * The guards run:
 *
 * - as `updating` and `deleting` events registered while booting, so they fire before package
 *   events such as Spatie's pivot cleanup;
 * - directly from the save and delete paths when model events are silenced
 *   (`saveQuietly()`, `deleteQuietly()`, `withoutEvents()`, `WithoutModelEvents` seeders);
 * - and query-builder bulk writes, which skip both, are refused by {@see GuardedBuilder}.
 */
trait GuardsInvariants
{
    protected static function booting(): void
    {
        static::updating(fn (self $model) => $model->guardUpdate());
        static::deleting(fn (self $model) => $model->guardDelete());
    }

    /**
     * Throw a domain exception when the pending changes would break an invariant.
     */
    protected function guardUpdate(): void {}

    /**
     * Throw a domain exception when deleting the record would break an invariant.
     */
    protected function guardDelete(): void {}

    /**
     * @param  Builder<static>  $query
     */
    protected function performUpdate(Builder $query): bool
    {
        if (self::modelEventsAreSilenced() && $this->isDirty()) {
            $this->guardUpdate();
        }

        return parent::performUpdate($query);
    }

    protected function performDeleteOnModel(): void
    {
        if (self::modelEventsAreSilenced()) {
            $this->guardDelete();
        }

        parent::performDeleteOnModel();
    }

    /**
     * Whether the `updating` and `deleting` guards will not fire for this write.
     */
    private static function modelEventsAreSilenced(): bool
    {
        $dispatcher = static::getEventDispatcher();

        return $dispatcher === null || $dispatcher instanceof NullDispatcher;
    }

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
