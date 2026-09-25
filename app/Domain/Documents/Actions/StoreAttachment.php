<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Documents\Contracts\AttachmentScanner;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Exceptions\RejectedUpload;
use App\Domain\Documents\Models\Attachment;
use App\Domain\Documents\Support\AttachmentRules;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The only way files enter PACE. Checks the extension against the
 * allow-list, sniffs the real content type, enforces the size limit, hashes
 * the file, runs the antivirus hook, and stores it under a random name on
 * the private documents disk.
 */
class StoreAttachment
{
    public function __construct(
        private readonly AttachmentRules $rules,
        private readonly AttachmentScanner $scanner,
        private readonly CurrentEntity $currentEntity,
    ) {}

    public function handle(Model $attachable, UploadedFile $file, string $category, ?User $by = null, string $field = 'file'): Attachment
    {
        $entity = $this->currentEntity->require();

        if ((int) $attachable->getAttribute('entity_id') !== $entity->getKey()) {
            throw RejectedUpload::because(__('The file cannot be attached to a record of another entity.'), $field);
        }

        $extension = Str::lower($file->getClientOriginalExtension());

        if (! in_array($extension, $this->rules->allowedExtensions(), true)) {
            throw RejectedUpload::because(__('Files of type .:ext are not allowed. Allowed: :list.', [
                'ext' => $extension ?: '?',
                'list' => implode(', ', $this->rules->allowedExtensions()),
            ]), $field);
        }

        $path = $file->getRealPath();
        $size = (int) $file->getSize();

        if ($path === false || $size <= 0) {
            throw RejectedUpload::because(__('The file is empty or could not be read.'), $field);
        }

        if ($size > $this->rules->maxBytes()) {
            throw RejectedUpload::because(__('The file is larger than :mb MB.', ['mb' => intdiv($this->rules->maxBytes(), 1048576)]), $field);
        }

        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! $this->rules->contentMatches($extension, $detected, $path)) {
            throw RejectedUpload::because(__('The file content does not match its .:ext extension.', ['ext' => $extension]), $field);
        }

        $status = $this->scanner->scan($path);

        if ($status === ScanStatus::Infected) {
            activity('documents')->causedBy($by)->event('upload_blocked')
                ->withProperties(['original_name' => $file->getClientOriginalName(), 'reason' => 'infected'])
                ->log('Upload blocked by virus scan');

            throw RejectedUpload::because(__('The file was blocked by the virus scanner.'), $field);
        }

        $disk = (string) config('filesystems.documents_disk', 'documents');
        $storedPath = sprintf('entity-%d/%s/%s', $entity->getKey(), now()->format('Y/m'), Str::uuid()->toString());

        Storage::disk($disk)->putFileAs(dirname($storedPath), $file, basename($storedPath));

        $attachment = new Attachment([
            'category' => $category,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'disk' => $disk,
            'path' => $storedPath,
            'extension' => $extension,
            'mime_type' => $detected,
            'size' => $size,
            'sha256' => (string) hash_file('sha256', $path),
            'scan_status' => $status,
            'scanned_at' => now(),
            'uploaded_by' => $by?->getKey(),
        ]);
        $attachment->attachable()->associate($attachable);
        $attachment->save();

        activity('documents')
            ->performedOn($attachable)
            ->causedBy($by)
            ->event('attachment_added')
            ->withProperties(['attachment_id' => $attachment->id, 'name' => $attachment->original_name, 'sha256' => $attachment->sha256, 'category' => $category])
            ->log('File attached');

        return $attachment;
    }
}
