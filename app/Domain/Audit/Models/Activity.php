<?php

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Support\AppendOnly;
use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Audit trail entry. Extends the Spatie model with entity, IP, user agent,
 * request id and "on behalf of" (delegation). Append-only.
 *
 * @property int|null $entity_id
 * @property int|null $on_behalf_of_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_id
 */
class Activity extends SpatieActivity
{
    use AppendOnly;

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_id');
    }
}
