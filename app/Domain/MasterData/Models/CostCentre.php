<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Concerns\IsMasterData;
use Database\Factories\CostCentreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cost centre with an owner (an approval step assignee option) and a department.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $department_id
 * @property int|null $owner_user_id
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'effective_from', 'effective_to', 'department_id', 'owner_user_id'])]
#[UseFactory(CostCentreFactory::class)]
class CostCentre extends Model
{
    /** @use HasFactory<CostCentreFactory> */
    use HasFactory, IsMasterData;

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
