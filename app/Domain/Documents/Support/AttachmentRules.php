<?php

namespace App\Domain\Documents\Support;

use App\Domain\Core\Settings\Settings;

/**
 * The hard allow-list of file types PACE accepts, with the MIME types the
 * server may detect for each (content sniffing, not the browser's claim).
 * Admins can narrow the list in System settings but never widen it.
 */
final class AttachmentRules
{
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'docx', 'msg'];

    /** @var array<string, list<string>> */
    public const MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        // Office Open XML files are ZIP containers; libmagic may report either.
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        // Outlook messages are OLE compound files.
        'msg' => ['application/vnd.ms-outlook', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
    ];

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        /** @var list<string> $configured */
        $configured = (array) $this->settings->get('attachments.allowed_extensions');

        return array_values(array_intersect(self::EXTENSIONS, array_map('strtolower', $configured)));
    }

    public function maxBytes(): int
    {
        return $this->settings->int('attachments.max_size_mb') * 1024 * 1024;
    }

    public function maxKilobytes(): int
    {
        return intdiv($this->maxBytes(), 1024);
    }

    /** Leading bytes required when content sniffing only gives a generic container type. */
    private const SIGNATURES = [
        'xlsx' => "PK\x03\x04",
        'docx' => "PK\x03\x04",
        'msg' => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1",
    ];

    /**
     * Whether the sniffed content type (and, for container formats, the file
     * signature) is consistent with the extension.
     */
    public function contentMatches(string $extension, string $detectedMime, string $localPath): bool
    {
        if (! in_array($detectedMime, self::MIME_TYPES[$extension] ?? [], true)) {
            return false;
        }

        if (! isset(self::SIGNATURES[$extension])) {
            return true;
        }

        $signature = self::SIGNATURES[$extension];
        $head = (string) file_get_contents($localPath, false, null, 0, strlen($signature));

        return $head === $signature;
    }

    /**
     * MIME types for browser file pickers.
     *
     * @return list<string>
     */
    public function acceptedMimeTypes(): array
    {
        $types = [];

        foreach ($this->allowedExtensions() as $ext) {
            $types[] = self::MIME_TYPES[$ext][0];
        }

        return array_values(array_unique([...$types, 'application/vnd.ms-outlook', '.msg']));
    }
}
