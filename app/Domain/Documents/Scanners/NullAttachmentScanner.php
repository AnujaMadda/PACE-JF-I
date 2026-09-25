<?php

namespace App\Domain\Documents\Scanners;

use App\Domain\Documents\Contracts\AttachmentScanner;
use App\Domain\Documents\Enums\ScanStatus;

/**
 * Default until an antivirus service (e.g. ClamAV) is available.
 */
class NullAttachmentScanner implements AttachmentScanner
{
    public function scan(string $localPath): ScanStatus
    {
        return ScanStatus::NotScanned;
    }
}
