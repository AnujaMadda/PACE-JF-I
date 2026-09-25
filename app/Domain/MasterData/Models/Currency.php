<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Core\Models\Entity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ISO 4217 currency. Group-wide (not entity-owned); each entity enables the
 * currencies it transacts in.
 *
 * @property string $code
 * @property string $name
 * @property int $decimals
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'decimals', 'is_active'])]
class Currency extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['decimals' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Entity, $this>
     */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_currency', 'currency_code', 'entity_id')->withTimestamps();
    }

    /**
     * Currencies enabled for an entity (its base currency is always included).
     *
     * @param  Builder<Currency>  $query
     */
    public function scopeEnabledFor(Builder $query, Entity $entity): void
    {
        $query->where('is_active', true)->where(fn (Builder $q) => $q
            ->where('code', $entity->base_currency)
            ->orWhereHas('entities', fn (Builder $e) => $e->whereKey($entity->getKey())));
    }

    /**
     * @return array<string, string>
     */
    public static function optionsFor(Entity $entity): array
    {
        return self::query()->enabledFor($entity)->orderBy('code')->get()
            ->mapWithKeys(fn (Currency $c) => [$c->code => "{$c->code} — {$c->name}"])->all();
    }
}
