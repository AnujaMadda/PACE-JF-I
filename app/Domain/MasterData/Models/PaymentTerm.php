<?php

namespace App\Domain\MasterData\Models;

use App\Domain\MasterData\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Vendor payment terms, e.g. Net 30.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int $days
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'days'])]
class PaymentTerm extends Model
{
    use IsMasterData;

    protected function casts(): array
    {
        return [
            'days' => 'integer',
        ];
    }
}
