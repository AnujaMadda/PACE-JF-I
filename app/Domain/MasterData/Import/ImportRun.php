<?php

namespace App\Domain\MasterData\Import;

use App\Domain\Core\Concerns\BelongsToEntity;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One uploaded import file and its outcome.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $type
 * @property ImportStatus $status
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property string|null $error_report_path
 * @property int $total_rows
 * @property int $created_rows
 * @property int $updated_rows
 * @property int $failed_rows
 * @property string|null $message
 * @property array<string, mixed>|null $options
 * @property int|null $created_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
#[Fillable(['type', 'status', 'original_name', 'disk', 'path', 'error_report_path', 'total_rows', 'created_rows', 'updated_rows', 'failed_rows', 'message', 'options', 'created_by', 'started_at', 'finished_at'])]
class ImportRun extends Model
{
    use BelongsToEntity;

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function definition(): ImportDefinition
    {
        return app(ImportRegistry::class)->get($this->type);
    }
}
