<?php

namespace App\Domain\Documents\Enums;

enum ScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    /** No scanner configured (NullAttachmentScanner). Downloads are allowed. */
    case NotScanned = 'not_scanned';

    public function allowsDownload(): bool
    {
        return in_array($this, [self::Clean, self::NotScanned], true);
    }
}
