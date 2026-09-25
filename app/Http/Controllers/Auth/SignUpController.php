<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Actions\RequestActivation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SignUpController extends Controller
{
    public function create(Settings $settings): View
    {
        return view('auth.sign-up', ['selfRegistration' => $settings->bool('auth.self_registration')]);
    }

    public function store(Request $request, RequestActivation $requestActivation): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $requestActivation->handle($data['email'], $data['name'] ?? null);

        return back()->with('status', __('If this email is registered for PACE, we have sent a link to set your password. Please check your inbox.'));
    }
}
