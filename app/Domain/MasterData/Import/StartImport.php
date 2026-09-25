<?php

namespace App\Domain\MasterData\Import;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores the uploaded workbook privately and queues it for processing.
 */
class StartImport
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly CurrentEntity $currentEntity,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(string $type, UploadedFile $file, User $by, array $options = []): ImportRun
    {
        $definition = $this->registry->get($type);
        $entity = $this->currentEntity->require();

        if (! $by->can($definition->importPermission())) {
            throw new AuthorizationException;
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        $head = (string) file_get_contents((string) $file->getRealPath(), false, null, 0, 4);

        if ($extension !== 'xlsx' || $head !== "PK\x03\x04" || $file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => __('Upload an Excel workbook (.xlsx) of up to 10 MB.')]);
        }

        $disk = (string) config('filesystems.documents_disk', 'documents');
        $path = sprintf('entity-%d/imports/%s.xlsx', $entity->getKey(), Str::uuid());
        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        $run = ImportRun::query()->create([
            'type' => $type,
            'status' => ImportStatus::Queued,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'disk' => $disk,
            'path' => $path,
            'options' => $options,
            'created_by' => $by->getKey(),
        ]);

        ProcessImport::dispatch($run->getKey(), $entity->getKey());

        return $run->refresh();
    }
}
