<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logs the current session out (logout, idle timeout, revoked access) and records why.
 */
class EndSession
{
    public function __construct(private readonly RecordLoginEvent $recordLoginEvent) {}

    public function handle(Request $request, User $user, LoginEventType $event, ?string $reason = null): void
    {
        $entityId = $request->session()->get('entity_id');

        $this->recordLoginEvent->handle($request, $event, $user->email, $user, is_int($entityId) ? $entityId : null, $reason);

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event($event->value)
            ->withProperties(array_filter(['reason' => $reason]))
            ->log($event->label());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
