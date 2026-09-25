<?php

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\Enums\ScanStatus;

/**
 * Antivirus hook. Called for every upload before the attachment is saved.
 * Implementations receive a readable local path to the uploaded file.
 */
interface AttachmentScanner
{
    public function scan(string $localPath): ScanStatus;
}
