<?php

use App\Domain\Core\Exceptions\EntityMismatch;
use App\Domain\Core\Exceptions\NoCurrentEntity;
use App\Domain\Core\Scopes\EntityScope;
use App\Domain\Core\Support\CurrentEntity;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\IsolationWidget;

beforeEach(function () {
    IsolationWidget::createTable();
    $this->kenya = kenya();
    $this->uae = makeEntity(['code' => 'AE']);
    $this->current = app(CurrentEntity::class);

    $this->current->run($this->kenya, fn () => IsolationWidget::query()->create(['name' => 'Kenya widget']));
    $this->current->run($this->uae, fn () => IsolationWidget::query()->create(['name' => 'UAE widget']));
});

it('only returns records of the current entity', function () {
    $this->current->set($this->kenya);

    expect(IsolationWidget::query()->pluck('name')->all())->toBe(['Kenya widget']);
});

it('cannot load another entity\'s record by id', function () {
    $uaeId = IsolationWidget::query()->withoutGlobalScope(EntityScope::class)->where('name', 'UAE widget')->value('id');
    $this->current->set($this->kenya);

    expect(IsolationWidget::query()->find($uaeId))->toBeNull();
    expect(fn () => IsolationWidget::query()->findOrFail($uaeId))->toThrow(ModelNotFoundException::class);
});

it('returns nothing when no entity is set (fails closed)', function () {
    $this->current->set(null);

    expect(IsolationWidget::query()->count())->toBe(0);
});

it('stamps the current entity on create', function () {
    $this->current->set($this->kenya);

    expect(IsolationWidget::query()->create(['name' => 'New'])->entity_id)->toBe($this->kenya->id);
});

it('never accepts entity_id from mass assignment', function () {
    $this->current->set($this->kenya);

    IsolationWidget::query()->create(['name' => 'New', 'entity_id' => $this->uae->id]);
})->throws(MassAssignmentException::class);

it('refuses to create a record for another entity', function () {
    $this->current->set($this->kenya);

    $widget = new IsolationWidget(['name' => 'Sneaky']);
    $widget->entity_id = $this->uae->id;

    expect(fn () => $widget->save())->toThrow(EntityMismatch::class);
});

it('refuses to move a record to another entity', function () {
    $this->current->set($this->kenya);
    $widget = IsolationWidget::query()->firstOrFail();

    $widget->entity_id = $this->uae->id;

    expect(fn () => $widget->save())->toThrow(EntityMismatch::class);
});

it('refuses to create records with no current entity', function () {
    $this->current->set(null);

    expect(fn () => IsolationWidget::query()->create(['name' => 'Orphan']))->toThrow(NoCurrentEntity::class);
});

it('scopes updates and deletes to the current entity too', function () {
    $this->current->set($this->kenya);

    IsolationWidget::query()->update(['name' => 'Renamed']);
    IsolationWidget::query()->delete();

    $this->current->set($this->uae);
    expect(IsolationWidget::query()->pluck('name')->all())->toBe(['UAE widget']);
});
