<?php

namespace App\Domain\MasterData\Models;

use App\Domain\MasterData\Concerns\IsMasterData;
use App\Domain\MasterData\Enums\GlAccountType;
use Database\Factories\GlAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A general ledger account. The type decides where it can be used (the Capex form offers Capex accounts only).
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property GlAccountType $type
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'type'])]
#[UseFactory(GlAccountFactory::class)]
class GlAccount extends Model
{
    /** @use HasFactory<GlAccountFactory> */
    use HasFactory, IsMasterData;

    protected function casts(): array
    {
        return [
            'type' => GlAccountType::class,
        ];
    }

    /**
     * @param  Builder<GlAccount>  $query
     */
    public function scopeOfType(Builder $query, GlAccountType $type): void
    {
        $query->where('type', $type);
    }
}
