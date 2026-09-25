<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Core\Money\DecimalCast;
use App\Domain\Documents\Concerns\HasAttachments;
use App\Domain\MasterData\Concerns\IsMasterData;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A board paper approving expenditure, with its supporting document.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon $paper_date
 * @property BigDecimal $approved_amount
 * @property string $currency_code
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'paper_date', 'approved_amount', 'currency_code'])]
class BoardPaper extends Model
{
    use HasAttachments, IsMasterData;

    protected function casts(): array
    {
        return [
            'paper_date' => 'date',
            'approved_amount' => DecimalCast::money(),
        ];
    }
}
