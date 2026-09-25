<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, SendPasswordResetLink $sendLink): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $sendLink->handle($data['email']);

        return back()->with('status', __('If an active account uses this email, we have sent a password reset link.'));
    }
}
