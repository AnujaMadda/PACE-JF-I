<?php

namespace App\Domain\Core\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Timestamps are stored in UTC and shown in the current entity's timezone.
 * Use @localtime($value) in Blade; Filament uses FilamentTimezone (set per request).
 */
final class LocalTime
{
    public static function timezone(): string
    {
        return app(CurrentEntity::class)->get()->timezone ?? 'UTC';
    }

    public static function format(DateTimeInterface|string|null $value, string $format = 'd M Y, H:i'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value, 'UTC');

        return $date->setTimezone(self::timezone())->format($format);
    }
}
