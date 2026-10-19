<?php

namespace ShipKit\Core\Eloquent;

/**
 * Every public method of Laravel's Eloquent and query builders, classified by whether it can write
 * around a guarded model's invariants. Built for Laravel 13; a test fails when the installed
 * Laravel has a method missing from these lists, so a framework upgrade cannot quietly add a route
 * around the guards.
 */
final class BuilderMethods
{
    /**
     * Writes that run only on the guard trait's own single-record path (save, delete, and an
     * instance's increment or decrement), and are refused everywhere else.
     *
     * @var list<string>
     */
    public const array GUARDED = [
        'update', 'delete', 'insert', 'insertGetId', 'increment', 'decrement', 'incrementEach', 'decrementEach',
    ];

    /**
     * Writes that always bypass the guards, so a guarded model's builder refuses them.
     *
     * @var list<string>
     */
    public const array REJECTED = [
        'forceDelete', 'truncate', 'touch', 'upsert', 'updateOrInsert', 'updateFrom',
        'insertOrIgnore', 'insertOrIgnoreReturning', 'insertUsing', 'insertOrIgnoreUsing',
        'fillAndInsert', 'fillAndInsertOrIgnore', 'fillAndInsertGetId',
    ];

    /**
     * Reads, query building, and writes that go through the model (`create()`, `updateOrCreate()`),
     * so the guards run. `toBase()`, `getQuery()`, and `getConnection()` hand out lower-level
     * builders; the guarded-tables check refuses writes made through them.
     *
     * @var list<string>
     */
    public const array SAFE = [
        'addBinding', 'addNestedHavingQuery', 'addNestedWhereQuery', 'addSelect', 'addWhereExistsQuery',
        'afterQuery', 'aggregate', 'applyAfterQueryCallbacks', 'applyBeforeQueryCallbacks', 'applyScopes',
        'average', 'avg', 'baseSole', 'beforeQuery', 'castBinding', 'chunk', 'chunkById', 'chunkByIdDesc',
        'chunkMap', 'cleanBindings', 'clone', 'cloneWithout', 'cloneWithoutBindings', 'count', 'create',
        'createOrFirst', 'createQuietly', 'crossJoin', 'crossJoinSub', 'cursor', 'cursorPaginate', 'dd',
        'ddRawSql', 'distinct', 'doesntExist', 'doesntExistOr', 'doesntHave', 'doesntHaveMorph', 'dump',
        'dumpRawSql', 'dynamicWhere', 'each', 'eachById', 'eagerLoadRelations', 'except', 'exists',
        'existsOr', 'explain', 'fetchUsing', 'fillForInsert', 'find', 'findMany', 'findOr', 'findOrFail',
        'findOrNew', 'findSole', 'first', 'firstOr', 'firstOrCreate', 'firstOrFail', 'firstOrNew',
        'firstWhere', 'flushMacros', 'forNestedWhere', 'forPage', 'forPageAfterId', 'forPageBeforeId',
        'forceCreate', 'forceCreateQuietly', 'forceIndex', 'from', 'fromQuery', 'fromRaw', 'fromSub', 'get',
        'getBindings', 'getColumns', 'getConnection', 'getCountForPagination', 'getEagerLoads',
        'getGlobalMacro', 'getGrammar', 'getLimit', 'getMacro', 'getModel', 'getModels', 'getOffset',
        'getProcessor', 'getQuery', 'getRawBindings', 'getRelation', 'groupBy', 'groupByRaw', 'groupLimit',
        'has', 'hasGlobalMacro', 'hasMacro', 'hasMorph', 'hasNamedScope', 'having', 'havingBetween',
        'havingNested', 'havingNotBetween', 'havingNotNull', 'havingNull', 'havingRaw', 'hydrate',
        'ignoreIndex', 'implode', 'inOrderOf', 'inRandomOrder', 'incrementOrCreate', 'join', 'joinLateral',
        'joinSub', 'joinWhere', 'latest', 'lazy', 'lazyById', 'lazyByIdDesc', 'leftJoin', 'leftJoinLateral',
        'leftJoinSub', 'leftJoinWhere', 'limit', 'lock', 'lockForUpdate', 'macro', 'macroCall', 'make',
        'max', 'mergeBindings', 'mergeConstraintsFrom', 'mergeWheres', 'min', 'mixin', 'modelKeys',
        'newModelInstance', 'newQuery', 'numericAggregate', 'offset', 'oldest', 'onClone', 'onDelete',
        'orDoesntHave', 'orDoesntHaveMorph', 'orHas', 'orHasMorph', 'orHaving', 'orHavingBetween',
        'orHavingNotBetween', 'orHavingNotNull', 'orHavingNull', 'orHavingRaw', 'orWhere',
        'orWhereAfterToday', 'orWhereAll', 'orWhereAny', 'orWhereAttachedTo', 'orWhereBeforeToday',
        'orWhereBelongsTo', 'orWhereBetween', 'orWhereBetweenColumns', 'orWhereBinary', 'orWhereColumn',
        'orWhereDate', 'orWhereDay', 'orWhereDoesntHave', 'orWhereDoesntHaveMorph',
        'orWhereDoesntHaveRelation', 'orWhereExists', 'orWhereFullText', 'orWhereFuture', 'orWhereHas',
        'orWhereHasMorph', 'orWhereIn', 'orWhereIntegerInRaw', 'orWhereIntegerNotInRaw',
        'orWhereJsonContains', 'orWhereJsonContainsKey', 'orWhereJsonDoesntContain',
        'orWhereJsonDoesntContainKey', 'orWhereJsonDoesntOverlap', 'orWhereJsonLength',
        'orWhereJsonOverlaps', 'orWhereKey', 'orWhereKeyNot', 'orWhereLike', 'orWhereMonth',
        'orWhereMorphDoesntHaveRelation', 'orWhereMorphRelation', 'orWhereMorphedTo', 'orWhereNone',
        'orWhereNot', 'orWhereNotBetween', 'orWhereNotBetweenColumns', 'orWhereNotBinary',
        'orWhereNotExists', 'orWhereNotIn', 'orWhereNotLike', 'orWhereNotMorphedTo', 'orWhereNotNull',
        'orWhereNowOrFuture', 'orWhereNowOrPast', 'orWhereNull', 'orWhereNullSafeEquals', 'orWherePast',
        'orWhereRaw', 'orWhereRelation', 'orWhereRowValues', 'orWhereTime', 'orWhereToday',
        'orWhereTodayOrAfter', 'orWhereTodayOrBefore', 'orWhereValueBetween', 'orWhereValueNotBetween',
        'orWhereVectorDistanceLessThan', 'orWhereYear', 'orderBy', 'orderByDesc', 'orderByRaw',
        'orderByVectorDistance', 'orderedChunkById', 'paginate', 'pipe', 'pluck', 'prepareValueAndOperator',
        'qualifyColumn', 'qualifyColumns', 'raw', 'rawValue', 'removedScopes', 'reorder', 'reorderDesc',
        'rightJoin', 'rightJoinSub', 'rightJoinWhere', 'scopes', 'select', 'selectExpression', 'selectRaw',
        'selectSub', 'selectVectorDistance', 'setBindings', 'setEagerLoads', 'setModel', 'setQuery',
        'sharedLock', 'simplePaginate', 'skip', 'sole', 'soleValue', 'straightJoin', 'straightJoinSub',
        'straightJoinWhere', 'sum', 'take', 'tap', 'timeout', 'toBase', 'toRawSql', 'toSql', 'union',
        'unionAll', 'unless', 'updateOrCreate', 'useIndex', 'useWritePdo', 'value', 'valueOrFail', 'when',
        'where', 'whereAfterToday', 'whereAll', 'whereAny', 'whereAttachedTo', 'whereBeforeToday',
        'whereBelongsTo', 'whereBetween', 'whereBetweenColumns', 'whereBinary', 'whereColumn', 'whereDate',
        'whereDay', 'whereDoesntHave', 'whereDoesntHaveMorph', 'whereDoesntHaveRelation', 'whereExists',
        'whereFullText', 'whereFuture', 'whereHas', 'whereHasMorph', 'whereIn', 'whereIntegerInRaw',
        'whereIntegerNotInRaw', 'whereJsonContains', 'whereJsonContainsKey', 'whereJsonDoesntContain',
        'whereJsonDoesntContainKey', 'whereJsonDoesntOverlap', 'whereJsonLength', 'whereJsonOverlaps',
        'whereKey', 'whereKeyNot', 'whereLike', 'whereMonth', 'whereMorphDoesntHaveRelation',
        'whereMorphRelation', 'whereMorphedTo', 'whereNested', 'whereNone', 'whereNot', 'whereNotBetween',
        'whereNotBetweenColumns', 'whereNotBinary', 'whereNotExists', 'whereNotIn', 'whereNotLike',
        'whereNotMorphedTo', 'whereNotNull', 'whereNowOrFuture', 'whereNowOrPast', 'whereNull',
        'whereNullSafeEquals', 'wherePast', 'whereRaw', 'whereRelation', 'whereRowValues', 'whereTime',
        'whereToday', 'whereTodayOrAfter', 'whereTodayOrBefore', 'whereValueBetween', 'whereValueNotBetween',
        'whereVectorDistanceLessThan', 'whereVectorSimilarTo', 'whereYear', 'with', 'withAggregate',
        'withAttributes', 'withAvg', 'withCasts', 'withCount', 'withExists', 'withGlobalScope', 'withMax',
        'withMin', 'withOnly', 'withSavepointIfNeeded', 'withSum', 'withWhereHas', 'withWhereRelation',
        'without', 'withoutEagerLoad', 'withoutEagerLoads', 'withoutGlobalScope', 'withoutGlobalScopes',
        'withoutGlobalScopesExcept',
    ];
}
