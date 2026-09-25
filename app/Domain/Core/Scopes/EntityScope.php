<?php

namespace App\Domain\Core\Scopes;

use App\Domain\Core\Support\CurrentEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits entity-owned models to the current entity. Fails closed: with no
 * current entity the query returns nothing. Cross-entity reporting must opt
 * out explicitly with withoutGlobalScope(EntityScope::class).
 *
 * @implements Scope<Model>
 */
class EntityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $entityId = app(CurrentEntity::class)->id();

        if ($entityId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('entity_id'), $entityId);
    }
}
