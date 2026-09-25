<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Actions\AttemptLogin;
use App\Domain\Identity\Actions\EndSession;
use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            // All active entities, not just the user's, so the form reveals nothing about memberships.
            'entities' => Entity::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, AttemptLogin $attemptLogin, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'entity_id' => ['required', 'integer'],
        ]);

        if (! $attemptLogin->handle($request, $data['email'], $data['password'], (int) $data['entity_id'])) {
            throw ValidationException::withMessages([
                'email' => __('These details don\'t match our records, or the account is not available. After :n failed attempts an account is locked for :m minutes.', [
                    'n' => $settings->int('security.lockout_max_attempts'),
                    'm' => $settings->int('security.lockout_minutes'),
                ]),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, EndSession $endSession): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $endSession->handle($request, $user, LoginEventType::Logout);

        return redirect()->route('login')->with('status', __('You have signed out.'));
    }
}
