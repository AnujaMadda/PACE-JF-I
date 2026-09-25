<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every web response.
 *
 * Alpine.js (used by Livewire and Filament) evaluates expressions at runtime,
 * so script-src currently needs 'unsafe-eval'. Tightening the CSP is a Phase 7 task.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id');
        $requestId = is_string($requestId) && preg_match('/^[A-Za-z0-9\-]{8,64}$/', $requestId) ? $requestId : (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Request-Id', $requestId);
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $dev = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", ...$dev],
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:', ...$dev],
            'connect-src' => ["'self'", ...$dev, ...array_map(fn (string $o) => preg_replace('/^http/', 'ws', $o), $dev)],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }

    /**
     * The Vite dev server origin while `npm run dev` is running locally.
     *
     * @return list<string>
     */
    private function viteDevServer(): array
    {
        $hot = public_path('hot');

        if (! app()->isLocal() || ! is_file($hot)) {
            return [];
        }

        return [rtrim(trim((string) file_get_contents($hot)), '/')];
    }
}
