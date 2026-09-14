<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening for every response (blueprint §22): CSP tuned to the prototype runtime
 * (in-browser Babel needs 'unsafe-eval' until backlog UI-02 precompiles the surfaces; React/Babel are
 * vendored under /surfaces/vendor so no third-party script host is allowed), HSTS in production,
 * MIME sniffing off, referrer trimmed, frames only from ourselves.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure() || app()->environment('production')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        if (! $headers->has('Content-Security-Policy') && str_starts_with((string) $headers->get('Content-Type', ''), 'text/html')) {
            $relay = (string) config('onhost.console.relay_url', '');
            $connect = "'self'".($relay !== '' ? ' '.$relay : '');
            $turnstile = (string) config('onhost.turnstile.site_key', '') !== '' ? ' https://challenges.cloudflare.com' : ''; // §5q-6
            $headers->set('Content-Security-Policy', implode('; ', array_filter([
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:{$turnstile}",   // prototype runtime: inline scripts + in-browser Babel (UI-02)
                $turnstile !== '' ? "frame-src{$turnstile}" : null,
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                'font-src \'self\' data: https://fonts.gstatic.com',
                "img-src 'self' data: blob:",
                "connect-src {$connect}",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ])).($request->isSecure() ? '; upgrade-insecure-requests' : ''));
        }

        return $response;
    }
}
