<?php

namespace App\Domain\MasterData\Models;

use App\Domain\MasterData\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A budget code. Budgets (Phase 3) are held per budget code and cost centre.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $gl_account_id
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'gl_account_id'])]
class BudgetCode extends Model
{
    use IsMasterData;

    /**
     * @return BelongsTo<GlAccount, $this>
     */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class);
    }
}
