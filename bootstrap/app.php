<?php

use App\Http\Middleware\EnsureEntitySelected;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Http\Middleware\IdleTimeout;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'entity' => EnsureEntitySelected::class,
            'idle' => IdleTimeout::class,
            'password.fresh' => EnsurePasswordNotExpired::class,
        ]);

        // Resolve the current entity (and permission team) before authentication checks such as
        // Filament's canAccessPanel(). Both let guests through untouched.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, IdleTimeout::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureEntitySelected::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
