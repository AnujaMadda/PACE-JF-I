<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Core\Concerns\BelongsToEntity;
use App\Domain\Core\Money\DecimalCast;
use Brick\Math\BigDecimal;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Effective-dated rate for an entity: 1 unit of from_currency = rate units of
 * to_currency. Rates are never edited in place for past dates; add a new row
 * with a later effective date instead.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $from_currency
 * @property string $to_currency
 * @property BigDecimal $rate
 * @property Carbon $effective_from
 * @property string|null $source
 * @property bool $is_active
 */
#[Fillable(['from_currency', 'to_currency', 'rate', 'effective_from', 'source', 'is_active'])]
#[UseFactory(ExchangeRateFactory::class)]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use BelongsToEntity, HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'rate' => DecimalCast::rate(),
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('master_data')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
