<?php

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ShipKit\Core\Eloquent\GuardsInvariants;
use ShipKit\Core\Exceptions\BulkWriteBypassesInvariants;
use ShipKit\Core\Exceptions\DomainException;
use ShipKit\Core\GuardedModelMap;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A locked widget refuses every change, so a write that skips the guards is visible.
 */
#[Unguarded]
#[Table(name: 'shipkit_guarded_widgets')]
class GuardedWidget extends Model
{
    use GuardsInvariants;

    protected function casts(): array
    {
        return ['locked' => 'boolean'];
    }

    protected function guardCreate(): void
    {
        if ($this->name === 'forbidden') {
            throw new WidgetIsLocked;
        }
    }

    protected function guardUpdate(): void
    {
        if ($this->getOriginal('locked')) {
            throw new WidgetIsLocked;
        }
    }

    protected function guardDelete(): void
    {
        if ($this->getOriginal('locked')) {
            throw new WidgetIsLocked;
        }
    }
}

class WidgetIsLocked extends DomainException
{
    public function field(): string
    {
        return 'widget';
    }
}

beforeEach(function () {
    Schema::create('shipkit_guarded_widgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('count')->default(0);
        $table->unsignedInteger('total')->default(0);
        $table->boolean('locked')->default(false);
        $table->timestamps();
    });

    $this->widget = GuardedWidget::create(['name' => 'locked', 'locked' => true]);

    $this->travel(5)->minutes();
});

/**
 * The widgets table as stored, so a test can prove a refused write changed nothing.
 *
 * @return list<array<string, mixed>>
 */
function storedWidgets(): array
{
    return DB::table('shipkit_guarded_widgets')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
}

it('refuses every builder write on a guarded model, even one aimed at a single row', function (Closure $write) {
    $before = storedWidgets();

    expect(fn () => $write(GuardedWidget::query()->whereKey($this->widget->getKey())))
        ->toThrow(BulkWriteBypassesInvariants::class);

    expect(storedWidgets())->toBe($before);
})->with([
    'update' => fn ($query) => $query->update(['name' => 'renamed']),
    'delete' => fn ($query) => $query->delete(),
    'forceDelete' => fn ($query) => $query->forceDelete(),
    'truncate' => fn ($query) => $query->truncate(),
    'insert' => fn ($query) => $query->insert(['name' => 'inserted']),
    'insertGetId' => fn ($query) => $query->insertGetId(['name' => 'inserted']),
    'insertOrIgnore' => fn ($query) => $query->insertOrIgnore(['name' => 'inserted']),
    'insertOrIgnoreReturning' => fn ($query) => $query->insertOrIgnoreReturning(['name' => 'inserted']),
    'insertUsing' => fn ($query) => $query->insertUsing(['name'], DB::table('shipkit_guarded_widgets')->select('name')),
    'insertOrIgnoreUsing' => fn ($query) => $query->insertOrIgnoreUsing(['name'], DB::table('shipkit_guarded_widgets')->select('name')),
    'fillAndInsert' => fn ($query) => $query->fillAndInsert([['name' => 'inserted']]),
    'fillAndInsertOrIgnore' => fn ($query) => $query->fillAndInsertOrIgnore([['name' => 'inserted']]),
    'fillAndInsertGetId' => fn ($query) => $query->fillAndInsertGetId(['name' => 'inserted']),
    'upsert' => fn ($query) => $query->upsert([['id' => 1, 'name' => 'renamed']], ['id'], ['name']),
    'updateOrInsert' => fn ($query) => $query->updateOrInsert(['id' => 1], ['name' => 'renamed']),
    'updateFrom' => fn ($query) => $query->updateFrom(['name' => 'renamed']),
    'increment' => fn ($query) => $query->increment('count'),
    'decrement' => fn ($query) => $query->decrement('count'),
    'incrementEach' => fn ($query) => $query->incrementEach(['count' => 1]),
    'decrementEach' => fn ($query) => $query->decrementEach(['count' => 1]),
    'touch' => fn ($query) => $query->touch(),
]);

it('refuses builder writes on every mapped guarded model', function (string $key, string $write) {
    $model = app(GuardedModelMap::class)->{$key}();
    $query = $model::query()->whereKey(0);

    $attempt = match ($write) {
        'update' => fn () => $query->update(['updated_at' => now()]),
        'delete' => fn () => $query->delete(),
        'increment' => fn () => $query->increment('id'),
        'touch' => fn () => $query->touch(),
        'truncate' => fn () => $query->truncate(),
        'updateOrInsert' => fn () => $query->updateOrInsert(['id' => 0], ['updated_at' => now()]),
    };

    expect($attempt)->toThrow(BulkWriteBypassesInvariants::class);
})->with(GuardedModelMap::KEYS)->with(['update', 'delete', 'increment', 'touch', 'truncate', 'updateOrInsert']);

it('runs the creation guard however the record is inserted', function (Closure $insert) {
    expect(fn () => $insert(new GuardedWidget(['name' => 'forbidden'])))->toThrow(WidgetIsLocked::class);

    expect(GuardedWidget::query()->where('name', 'forbidden')->exists())->toBeFalse();
})->with([
    'save' => fn (GuardedWidget $widget) => $widget->save(),
    'save with model events silenced' => fn (GuardedWidget $widget) => GuardedWidget::withoutEvents(fn () => $widget->save()),
    'saveOrIgnore' => fn (GuardedWidget $widget) => $widget->saveOrIgnore(),
    'saveOrIgnore with model events silenced' => fn (GuardedWidget $widget) => GuardedWidget::withoutEvents(fn () => $widget->saveOrIgnore()),
]);

it('offers no way to mark a query as a single-record write', function () {
    $before = storedWidgets();

    expect(fn () => GuardedWidget::query()->forSingleRecordWrite()->delete())->toThrow(BadMethodCallException::class);

    expect(storedWidgets())->toBe($before);
});

it('refuses an increment on an unsaved model, which would change every row', function () {
    expect(fn () => (new GuardedWidget)->increment('count'))->toThrow(BulkWriteBypassesInvariants::class);
});

it('runs the guards on every instance write, with and without model events', function (Closure $write, bool $silenced) {
    $before = storedWidgets();
    $widget = $this->widget;

    $attempt = $silenced ? fn () => GuardedWidget::withoutEvents(fn () => $write($widget)) : fn () => $write($widget);

    expect($attempt)->toThrow(WidgetIsLocked::class);

    expect(storedWidgets())->toBe($before);
})->with([
    'save' => fn (GuardedWidget $widget) => $widget->forceFill(['name' => 'renamed'])->save(),
    'update' => fn (GuardedWidget $widget) => $widget->update(['name' => 'renamed']),
    'increment' => fn (GuardedWidget $widget) => $widget->increment('count'),
    'decrement' => fn (GuardedWidget $widget) => $widget->decrement('total'),
    'incrementEach' => fn (GuardedWidget $widget) => $widget->incrementEach(['count' => 1, 'total' => 2]),
    'decrementEach' => fn (GuardedWidget $widget) => $widget->decrementEach(['total' => 1]),
    'touch' => fn (GuardedWidget $widget) => $widget->touch(),
    'delete' => fn (GuardedWidget $widget) => $widget->delete(),
])->with([
    'with model events' => false,
    'with model events silenced' => true,
]);

it('lets instance writes through when the guards allow them', function () {
    $widget = GuardedWidget::create(['name' => 'open']);

    $widget->increment('count', 2);
    $widget->decrement('count');
    $widget->incrementEach(['count' => 3, 'total' => 5]);
    $widget->update(['name' => 'renamed']);

    expect($widget->fresh())
        ->name->toBe('renamed')
        ->count->toBe(4)
        ->total->toBe(5);

    $widget->delete();

    expect(GuardedWidget::query()->whereKey($widget->getKey())->exists())->toBeFalse();
});
