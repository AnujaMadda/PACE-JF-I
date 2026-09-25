<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\SetPassword;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\PasswordPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token, PasswordPolicy $policy): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
            'passwordHelp' => $policy->description(),
        ]);
    }

    public function store(Request $request, PasswordPolicy $policy, SetPassword $setPassword): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::query()->where('email', Str::lower((string) $request->input('email')))->first();

        $request->validate(['password' => $policy->rules($user)]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($setPassword): void {
                if (! $user->isActive()) {
                    return;
                }

                $setPassword->handle($user, $password, 'reset');
            },
        );

        if ($status !== Password::PASSWORD_RESET || $user === null || ! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('This password reset link is invalid or has expired.')]);
        }

        return redirect()->route('login')->with('status', __('Your password has been reset. You can now sign in.'));
    }
}
