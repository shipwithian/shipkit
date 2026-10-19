<?php

namespace ShipKit\Core\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Events\NullDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Run an aggregate's invariant guards on every write, with no way around them.
 *
 * The model overrides {@see guardCreate()}, {@see guardUpdate()} and {@see guardDelete()} to call its
 * `ensure...()` methods. The guards run:
 *
 * - as `creating`, `updating` and `deleting` events registered while booting, so they fire before
 *   package events such as Spatie's pivot cleanup;
 * - directly from the insert, save and delete paths when model events are silenced
 *   (`createQuietly()`, `saveQuietly()`, `deleteQuietly()`, `withoutEvents()`, `WithoutModelEvents` seeders);
 * - and query-builder bulk writes, which skip both, are refused by {@see GuardedBuilder}:
 *   `update`, `delete`, `upsert`, `insert`, `insertGetId`, `insertOrIgnore`, and `insertUsing`.
 *
 * A guard that checks what else exists ("at least one owner", "at most 50 notes") must lock the rows
 * it counts, or their parent, with `lockForUpdate()`. The trait runs each guarded save and delete in
 * one transaction so that lock holds from the check until the write commits, and concurrent
 * requests cannot both pass.
 */
trait GuardsInvariants
{
    protected static function booting(): void
    {
        static::creating(fn (self $model) => $model->guardCreate());
        static::updating(fn (self $model) => $model->guardUpdate());
        static::deleting(fn (self $model) => $model->guardDelete());
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    /**
     * Throw a domain exception when inserting the record would break an invariant.
     */
    protected function guardCreate(): void {}

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

    /**
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        if (self::modelEventsAreSilenced()) {
            $this->guardCreate();
        }

        if ($query instanceof GuardedBuilder) {
            $query->forSingleRecordWrite();
        }

        return parent::performInsert($query);
    }

    protected function performDeleteOnModel(): void
    {
        if (self::modelEventsAreSilenced()) {
            $this->guardDelete();
        }

        parent::performDeleteOnModel();
    }

    /**
     * Whether the `creating`, `updating` and `deleting` guards will not fire for this write.
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
