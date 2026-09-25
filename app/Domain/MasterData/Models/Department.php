<?php

namespace App\Domain\MasterData\Models;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Concerns\IsMasterData;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A department. Its head (HoD) can be an approval step assignee.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $head_user_id
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'head_user_id'])]
#[UseFactory(DepartmentFactory::class)]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, IsMasterData;

    /**
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * @return HasMany<CostCentre, $this>
     */
    public function costCentres(): HasMany
    {
        return $this->hasMany(CostCentre::class);
    }
}
