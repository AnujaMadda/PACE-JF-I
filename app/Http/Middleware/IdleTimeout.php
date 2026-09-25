<?php

namespace App\Http\Middleware;

use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Actions\EndSession;
use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session after the configured idle period (security.session_idle_minutes).
 * SESSION_LIFETIME is an outer bound; this setting is the enforced idle limit.
 */
class IdleTimeout
{
    public const SESSION_KEY = 'last_activity_at';

    public function __construct(
        private readonly Settings $settings,
        private readonly EndSession $endSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $last = $request->session()->get(self::SESSION_KEY);
        $limit = $this->settings->int('security.session_idle_minutes') * 60;

        if (is_int($last) && now()->getTimestamp() - $last > $limit) {
            $this->endSession->handle($request, $user, LoginEventType::IdleTimeout);

            return redirect()->route('login')->with('status', __('You were signed out after a period of inactivity.'));
        }

        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());

        return $next($request);
    }
}
