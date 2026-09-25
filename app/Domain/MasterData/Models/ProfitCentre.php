<?php

namespace App\Domain\MasterData\Models;

use App\Domain\MasterData\Concerns\IsMasterData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A profit centre.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'effective_from', 'effective_to'])]
class ProfitCentre extends Model
{
    use IsMasterData;
}
