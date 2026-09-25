<?php

namespace App\Domain\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored setting value. A row with no entity is the group default; a row
 * with an entity overrides it for that entity. Read through Settings, never directly.
 *
 * @property int $id
 * @property int|null $entity_id
 * @property string $key
 * @property mixed $value
 * @property int|null $updated_by
 */
#[Fillable(['entity_id', 'key', 'value', 'updated_by'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
