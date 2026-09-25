<?php

namespace App\Domain\Identity\Models;

use App\Domain\Audit\Support\AppendOnly;
use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Enums\LoginEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Login history. Append-only.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $email
 * @property int|null $entity_id
 * @property LoginEventType $event
 * @property string|null $reason
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'email', 'entity_id', 'event', 'reason', 'ip_address', 'user_agent'])]
class LoginEvent extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event' => LoginEventType::class,
            'created_at' => 'datetime',
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
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
