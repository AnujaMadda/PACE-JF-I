<?php

namespace App\Policies;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Documents\Models\Attachment;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * A file can be downloaded by anyone allowed to view the record it belongs to,
 * in the same entity, once it has passed (or skipped) the virus scan.
 */
class AttachmentPolicy
{
    public function __construct(private readonly CurrentEntity $currentEntity) {}

    public function download(User $user, Attachment $attachment): bool
    {
        if ($attachment->entity_id !== $this->currentEntity->id() || ! $attachment->scan_status->allowsDownload()) {
            return false;
        }

        $parent = $attachment->attachable;

        return $parent !== null && Gate::forUser($user)->allows('view', $parent);
    }
}
