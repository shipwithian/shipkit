<?php

namespace ShipKit\Core\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Events\NullDispatcher;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Run an aggregate's invariant guards on every write, with no way around them.
 *
 * The model overrides {@see guardCreate()}, {@see guardUpdate()} and {@see guardDelete()} to call its
 * `ensure...()` methods. The guards run:
 *
 * - as `creating`, `updating` and `deleting` events registered while booting, so they fire before
 *   package events such as Spatie's pivot cleanup;
 * - directly from the insert, save, delete, and instance increment paths when model events are
 *   silenced (`createQuietly()`, `saveQuietly()`, `deleteQuietly()`, `withoutEvents()`,
 *   `WithoutModelEvents` seeders);
 * - and every query-builder write, which skips both, is refused by {@see GuardedBuilder}. Only the
 *   trait's own single-record write may use the builder, and it opens that path just for the one
 *   query it is about to run. {@see BuilderMethods} classifies every builder method.
 *
 * A guard that checks what else exists ("at least one owner", "at most 50 notes") must lock the rows
 * it counts, or their parent, with `lockForUpdate()`. The trait runs each guarded save, delete, and
 * increment in one transaction so that lock holds from the check until the write commits, and
 * concurrent requests cannot both pass.
 */
trait GuardsInvariants
{
    /**
     * The guarded write in progress (`insert`, `update`, or `delete`), or null outside one.
     */
    private ?string $guardedWrite = null;

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
        return $this->runGuardedWrite('update', fn (): bool => parent::performUpdate($query));
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        if (self::modelEventsAreSilenced()) {
            $this->guardCreate();
        }

        return $this->runGuardedWrite('insert', function () use ($query): bool {
            $this->openSingleRecordWrite($query);

            return parent::performInsert($query);
        });
    }

    /**
     * `saveOrIgnore()` inserts through the base query builder, so its creation guard must run here
     * when model events are silenced.
     *
     * @param  Builder<static>  $query
     * @param  array<int, string>|string|null  $uniqueBy
     */
    protected function performInsertOrIgnore(Builder $query, array|string|null $uniqueBy): bool
    {
        if (self::modelEventsAreSilenced()) {
            $this->guardCreate();
        }

        return parent::performInsertOrIgnore($query, $uniqueBy);
    }

    protected function performDeleteOnModel(): void
    {
        $this->runGuardedWrite('delete', fn () => parent::performDeleteOnModel());
    }

    /**
     * An instance increment writes this one record, so it runs the update guards in a transaction.
     * On an unsaved model Laravel would change every row, which the builder refuses.
     *
     * @param  string  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     * @param  string  $method
     * @return int|false
     */
    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        if (! $this->exists) {
            return parent::incrementOrDecrement($column, $amount, $extra, $method);
        }

        return DB::transaction(fn () => $this->runGuardedWrite('update', fn () => parent::incrementOrDecrement($column, $amount, $extra, $method)));
    }

    /**
     * @param  array<string, float|int>  $columns
     * @param  array<string, mixed>  $extra
     * @return int|false
     */
    protected function incrementOrDecrementEach(array $columns, array $extra, string $method)
    {
        if (! $this->exists) {
            return parent::incrementOrDecrementEach($columns, $extra, $method);
        }

        return DB::transaction(fn () => $this->runGuardedWrite('update', fn () => parent::incrementOrDecrementEach($columns, $extra, $method)));
    }

    /**
     * Key the query to this record and, during one of the trait's own writes, run the guard the
     * silenced events skipped and open the builder's single-record path for this query alone.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query): Builder
    {
        parent::setKeysForSaveQuery($query);

        if ($this->guardedWrite === 'update' || $this->guardedWrite === 'delete') {
            if (self::modelEventsAreSilenced() && $this->guardedWrite === 'delete') {
                $this->guardDelete();
            } elseif (self::modelEventsAreSilenced()) {
                $this->guardUpdate();
            }

            $this->openSingleRecordWrite($query);
        }

        return $query;
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
     * Run one of the trait's own writes, restoring the previous state afterwards so nested writes
     * (a pivot delete inside a model delete) keep their own.
     *
     * @template TResult
     *
     * @param  \Closure(): TResult  $write
     * @return TResult
     */
    private function runGuardedWrite(string $kind, \Closure $write): mixed
    {
        $previous = $this->guardedWrite;
        $this->guardedWrite = $kind;

        try {
            return $write();
        } finally {
            $this->guardedWrite = $previous;
        }
    }

    /**
     * Let this query run one guarded write. GuardedBuilder exposes no way to do this, so the flag is
     * set through a closure bound to the builder.
     *
     * @param  Builder<static>  $query
     */
    private function openSingleRecordWrite(Builder $query): void
    {
        if (! $query instanceof GuardedBuilder) {
            throw new LogicException(static::class.' must build queries with GuardedBuilder; do not override newEloquentBuilder().');
        }

        (fn () => $this->targetsSingleRecord = true)->call($query);
    }

    /**
     * Whether the `creating`, `updating` and `deleting` guards will not fire for this write.
     */
    private static function modelEventsAreSilenced(): bool
    {
        $dispatcher = static::getEventDispatcher();

        return $dispatcher === null || $dispatcher instanceof NullDispatcher;
    }
}
