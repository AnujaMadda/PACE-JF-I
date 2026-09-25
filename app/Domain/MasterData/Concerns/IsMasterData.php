<?php

namespace App\Domain\MasterData\Concerns;

use App\Domain\Core\Concerns\BelongsToEntity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Shared behaviour for per-entity master data: entity ownership and scoping,
 * the active flag (records are deactivated, never deleted), optional
 * effective dates, and an audit trail of every change.
 *
 * @mixin Model
 */
trait IsMasterData
{
    use BelongsToEntity, LogsActivity;

    public function initializeIsMasterData(): void
    {
        $this->mergeCasts(['is_active' => 'boolean']);

        if (in_array('effective_from', $this->getFillable(), true)) {
            $this->mergeCasts(['effective_from' => 'date', 'effective_to' => 'date']);
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * Active and within its effective dates (if the model has them) on the given day.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUsableOn(Builder $query, CarbonInterface $date): void
    {
        $query->where($this->qualifyColumn('is_active'), true);

        if (in_array('effective_from', $this->getFillable(), true)) {
            $day = $date->toDateString();
            $query->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('effective_from'))->orWhere($this->qualifyColumn('effective_from'), '<=', $day))
                ->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('effective_to'))->orWhere($this->qualifyColumn('effective_to'), '>=', $day));
        }
    }

    public function getLabelAttribute(): string
    {
        return $this->getAttribute('code').' — '.$this->getAttribute('name');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master_data')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
