<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Core\Settings\Settings;
use App\Domain\MasterData\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A Capex category or asset class. It can override the entity's minimum quotation count.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $minimum_quotations
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'minimum_quotations'])]
class CapexCategory extends Model
{
    use IsMasterData;

    protected function casts(): array
    {
        return [
            'minimum_quotations' => 'integer',
        ];
    }

    /**
     * Quotations required for requests in this category.
     */
    public function requiredQuotations(): int
    {
        return $this->minimum_quotations ?? app(Settings::class)->int('capex.minimum_quotations');
    }
}
