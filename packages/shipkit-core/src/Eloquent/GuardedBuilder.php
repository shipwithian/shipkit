<?php

namespace ShipKit\Core\Eloquent;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ShipKit\Core\Exceptions\BulkWriteBypassesInvariants;

/**
 * Query builder for aggregates whose invariants run as model events.
 *
 * Builder writes skip the guards, so every one is refused. The writes {@see BuilderMethods::GUARDED}
 * lists pass only on the single-record path {@see GuardsInvariants} opens for its own save, delete,
 * and instance increment; nothing outside the trait can open it.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class GuardedBuilder extends Builder
{
    /**
     * Set only by {@see GuardsInvariants}, through a closure bound to this class, and cleared by the
     * one write it allows.
     */
    private bool $targetsSingleRecord = false;

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        $this->ensureSingleRecordWrite('update');

        return parent::update($values);
    }

    public function delete(): mixed
    {
        $this->ensureSingleRecordWrite('delete');

        return parent::delete();
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $values
     */
    public function insert(array $values): bool
    {
        $this->ensureSingleRecordWrite('insert');

        return $this->toBase()->insert($values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  string|null  $sequence
     */
    public function insertGetId(array $values, $sequence = null): int|string
    {
        $this->ensureSingleRecordWrite('insertGetId');

        return $this->toBase()->insertGetId($values, $sequence);
    }

    /**
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     */
    public function increment($column, $amount = 1, array $extra = []): int
    {
        $this->ensureSingleRecordWrite('increment');

        return parent::increment($column, $amount, $extra);
    }

    /**
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
     */
    public function decrement($column, $amount = 1, array $extra = []): int
    {
        $this->ensureSingleRecordWrite('decrement');

        return parent::decrement($column, $amount, $extra);
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function incrementEach(array $columns, array $extra = []): int
    {
        $this->ensureSingleRecordWrite('incrementEach');

        return parent::incrementEach($columns, $extra);
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function decrementEach(array $columns, array $extra = []): int
    {
        $this->ensureSingleRecordWrite('decrementEach');

        return parent::decrementEach($columns, $extra);
    }

    public function forceDelete(): never
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'forceDelete');
    }

    /**
     * @param  array<int, string>|string|null  $column
     */
    public function touch($column = null): never
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'touch');
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'upsert');
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $values
     */
    public function insertOrIgnore(array $values): never
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'insertOrIgnore');
    }

    /**
     * @param  array<int, array<string, mixed>>  $values
     */
    public function fillAndInsert(array $values): never
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'fillAndInsert');
    }

    /**
     * @param  array<int, array<string, mixed>>  $values
     */
    public function fillAndInsertOrIgnore(array $values): never
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'fillAndInsertOrIgnore');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function fillAndInsertGetId(array $values): never
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'fillAndInsertGetId');
    }

    /**
     * Refuse the rejected writes that Eloquent forwards to the query builder, such as `truncate()`
     * and `updateOrInsert()`.
     *
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        if (in_array(strtolower($method), array_map(strtolower(...), BuilderMethods::REJECTED), true)) {
            throw BulkWriteBypassesInvariants::for($this->model, $method);
        }

        return parent::__call($method, $parameters);
    }

    private function ensureSingleRecordWrite(string $operation): void
    {
        if (! $this->targetsSingleRecord) {
            throw BulkWriteBypassesInvariants::for($this->model, $operation);
        }

        $this->targetsSingleRecord = false;
    }
}
