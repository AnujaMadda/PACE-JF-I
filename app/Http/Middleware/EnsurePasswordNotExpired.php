<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When password expiry is on, sends users with an expired password to the
 * change-password page before anything else.
 */
class EnsurePasswordNotExpired
{
    public function __construct(private readonly PasswordPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && $this->policy->isExpired($user) && ! $request->routeIs('password.change', 'password.change.update', 'logout')) {
            return redirect()->route('password.change')->with('status', __('Your password has expired. Please choose a new one.'));
        }

        return $next($request);
    }
}
