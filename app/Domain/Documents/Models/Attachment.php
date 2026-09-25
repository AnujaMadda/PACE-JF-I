<?php

namespace App\Domain\Documents\Models;

use App\Domain\Core\Concerns\BelongsToEntity;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * A stored file belonging to a record (board paper, quotation, PO, invoice ...).
 * Created only through StoreAttachment; downloaded only through
 * AttachmentController after the parent record's policy allows it.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $category
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property ScanStatus $scan_status
 * @property Carbon|null $scanned_at
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 */
#[Fillable(['category', 'original_name', 'disk', 'path', 'extension', 'mime_type', 'size', 'sha256', 'scan_status', 'scanned_at', 'uploaded_by'])]
class Attachment extends Model
{
    use BelongsToEntity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'scan_status' => ScanStatus::class,
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        return (string) Number::fileSize($this->size, 1);
    }

    public function downloadUrl(): string
    {
        return route('attachments.download', $this);
    }
}
