<?php

namespace App\Domain\Core\Concerns;

use App\Domain\Core\Exceptions\EntityMismatch;
use App\Domain\Core\Exceptions\NoCurrentEntity;
use App\Domain\Core\Models\Entity;
use App\Domain\Core\Scopes\EntityScope;
use App\Domain\Core\Support\CurrentEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every master-data and transactional model. Adds the entity global scope,
 * stamps entity_id on create from the current entity, and refuses to create
 * records for another entity or move a record between entities.
 *
 * Do not add entity_id to $fillable on models using this trait.
 *
 * @mixin Model
 */
trait BelongsToEntity
{
    public static function bootBelongsToEntity(): void
    {
        static::addGlobalScope(new EntityScope);

        static::creating(function (Model $model): void {
            $current = app(CurrentEntity::class)->id();
            $given = $model->getAttribute('entity_id');

            if ($current === null) {
                throw new NoCurrentEntity;
            }

            if ($given !== null && (int) $given !== $current) {
                throw EntityMismatch::onCreate($model::class, (int) $given, $current);
            }

            $model->setAttribute('entity_id', $current);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('entity_id')) {
                throw EntityMismatch::onUpdate($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
