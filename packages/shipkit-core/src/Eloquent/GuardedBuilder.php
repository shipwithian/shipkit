<?php

namespace ShipKit\Core\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ShipKit\Core\Exceptions\BulkWriteBypassesInvariants;

/**
 * Query builder for aggregates whose invariants run as model events.
 *
 * Bulk writes skip model events, so they are refused. Single-record saves,
 * inserts, and deletes are marked by {@see GuardsInvariants} and pass through.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class GuardedBuilder extends Builder
{
    private bool $targetsSingleRecord = false;

    /**
     * @return $this
     */
    public function forSingleRecordWrite(): static
    {
        $this->targetsSingleRecord = true;

        return $this;
    }

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

        return parent::insert($values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  string|null  $sequence
     */
    public function insertGetId(array $values, $sequence = null): int|string
    {
        $this->ensureSingleRecordWrite('insertGetId');

        return parent::insertGetId($values, $sequence);
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $values
     */
    public function insertOrIgnore(array $values): int
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'insertOrIgnore');
    }

    /**
     * @param  array<int, string>|string  $columns
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder<*>|string  $query
     */
    public function insertUsing($columns, $query): int
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'insertUsing');
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        throw BulkWriteBypassesInvariants::for($this->model, 'upsert');
    }

    private function ensureSingleRecordWrite(string $operation): void
    {
        if (! $this->targetsSingleRecord) {
            throw BulkWriteBypassesInvariants::for($this->model, $operation);
        }
    }
}
