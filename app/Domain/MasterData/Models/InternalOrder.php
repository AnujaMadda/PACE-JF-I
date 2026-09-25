<?php

namespace App\Domain\MasterData\Models;

use App\Domain\MasterData\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An internal order, optionally tied to a cost centre.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $cost_centre_id
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'effective_from', 'effective_to', 'cost_centre_id'])]
class InternalOrder extends Model
{
    use IsMasterData;

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }
}
