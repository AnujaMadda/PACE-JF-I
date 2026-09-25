<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\SetPassword;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\PasswordPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function edit(PasswordPolicy $policy): View
    {
        return view('auth.change-password', ['passwordHelp' => $policy->description()]);
    }

    public function update(Request $request, PasswordPolicy $policy, SetPassword $setPassword): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => $policy->rules($user),
        ]);

        $setPassword->handle($user, $data['password'], 'change');

        // Keep this session, end the others.
        $request->session()->regenerate();
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return redirect()->route('dashboard')->with('status', __('Your password has been changed.'));
    }
}
