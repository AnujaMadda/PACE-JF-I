<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\LoginEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordLoginEvent
{
    public function handle(
        Request $request,
        LoginEventType $event,
        string $email,
        ?User $user = null,
        ?int $entityId = null,
        ?string $reason = null,
    ): LoginEvent {
        return LoginEvent::query()->create([
            'user_id' => $user?->getKey(),
            'email' => Str::limit(Str::lower($email), 250, ''),
            'entity_id' => $entityId,
            'event' => $event,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }
}
