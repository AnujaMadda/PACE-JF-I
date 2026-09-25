<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Initials avatar rendered as an inline SVG. Replaces Filament's default
 * provider, which loads images from an external service (blocked by our CSP
 * and a needless personal-data leak).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = Str::of(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><rect width="64" height="64" fill="#1e293b"/><text x="50%%" y="50%%" dy=".35em" text-anchor="middle" font-family="system-ui,sans-serif" font-size="26" font-weight="600" fill="#ffffff">%s</text></svg>',
            e($initials),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
