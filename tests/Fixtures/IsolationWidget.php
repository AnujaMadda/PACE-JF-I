<?php

namespace Tests\Fixtures;

use App\Domain\Core\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stand-in for an entity-owned master-data or transactional model.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $name
 */
#[Fillable(['name'])]
class IsolationWidget extends Model
{
    use BelongsToEntity;

    public static function createTable(): void
    {
        Schema::create('isolation_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained();
            $table->string('name');
            $table->timestamps();
        });
    }
}
