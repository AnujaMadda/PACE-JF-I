<?php

namespace App\Domain\Identity\Models;

use App\Domain\Core\Models\Entity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * "User is away; delegate acts for them" for a date range. The workflow engine
 * (Phase 4) resolves delegations when assigning and acting on tasks.
 *
 * @property int $id
 * @property int $user_id
 * @property int $delegate_id
 * @property int|null $entity_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon|null $revoked_at
 */
#[Fillable(['delegate_id', 'entity_id', 'starts_at', 'ends_at', 'reason'])]
class Delegation extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @param  Builder<Delegation>  $query
     */
    public function scopeActiveAt(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();

        $query->whereNull('revoked_at')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('delegations')->logFillable()->logOnly(['revoked_at'])->logOnlyDirty();
    }
}
