<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\ActivateAccount;
use App\Domain\Identity\Actions\IssueActivationLink;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\PasswordPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles the signed activation link. The route is protected by the
 * "signed" middleware; this controller also checks the link is the latest one
 * issued and that the account is still waiting for activation.
 */
class ActivationController extends Controller
{
    public function show(Request $request, User $user, PasswordPolicy $policy): View|RedirectResponse
    {
        if (! IssueActivationLink::isCurrent($user, (int) $request->query('issued'))) {
            return $this->invalid();
        }

        return view('auth.activate', [
            'user' => $user,
            'action' => $request->fullUrl(),
            'passwordHelp' => $policy->description(),
        ]);
    }

    public function store(Request $request, User $user, PasswordPolicy $policy, ActivateAccount $activate): RedirectResponse
    {
        if (! IssueActivationLink::isCurrent($user, (int) $request->query('issued'))) {
            return $this->invalid();
        }

        $data = $request->validate(['password' => $policy->rules($user)]);

        $activate->handle($user, $data['password']);

        return redirect()->route('login')->with('status', __('Your account is active. You can now sign in.'));
    }

    private function invalid(): RedirectResponse
    {
        return redirect()->route('sign-up')->withErrors([
            'email' => __('This activation link is no longer valid. Enter your email to get a new one.'),
        ]);
    }
}
