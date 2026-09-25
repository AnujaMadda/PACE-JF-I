<?php

namespace App\Http\Middleware;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Actions\EndSession;
use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\User;
use Closure;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loads the entity chosen at login (or via the switcher) into CurrentEntity,
 * re-checking on every request that the user is still active, unlocked and
 * allowed in that entity. Anything else ends the session.
 */
class EnsureEntitySelected
{
    public function __construct(
        private readonly CurrentEntity $currentEntity,
        private readonly EndSession $endSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $entityId = $request->session()->get('entity_id');
        $entity = $entityId !== null ? Entity::query()->find($entityId) : null;

        if (! $user->isActive() || $user->isLocked() || $entity === null || ! $user->canAccessEntity($entity)) {
            $this->endSession->handle($request, $user, LoginEventType::ForcedLogout, 'access_revoked');

            return redirect()->route('login')->with('status', __('Your session has ended. Please sign in again.'));
        }

        $this->currentEntity->set($entity);
        FilamentTimezone::set($entity->timezone);
        View::share('currentEntity', $entity);

        return $next($request);
    }
}
