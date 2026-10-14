<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening for every response (blueprint §22): CSP tuned to the prototype runtime
 * (in-browser Babel needs 'unsafe-eval' until backlog UI-02 precompiles the surfaces; React/Babel are
 * vendored under /surfaces/vendor so no third-party script host is allowed), HSTS from configuration (P11, P-R74),
 * MIME sniffing off, referrer trimmed, frames only from ourselves.
 */
final class SecurityHeaders
{
    /** One year: the shortest max-age the HSTS preload list accepts. */
    private const PRELOAD_MIN_AGE = 31536000;

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
        $hsts = self::hstsValue();
        if ($hsts !== null && ($request->isSecure() || app()->environment('production'))) {
            $headers->set('Strict-Transport-Security', $hsts);
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

    /**
     * P11 (P-R74, HS-1): the Strict-Transport-Security value from `onhost.security.hsts`, null when it is off. `preload` is added
     * only with includeSubDomains and a max-age of at least one year — the preload list refuses anything else, so the word would
     * promise a listing that cannot happen. The doctor row "HSTS is sent once and as configured" compares the live header with this.
     */
    public static function hstsValue(): ?string
    {
        $config = (array) config('onhost.security.hsts', []);
        $maxAge = (int) ($config['max_age'] ?? 0);
        if (! (bool) ($config['enabled'] ?? true) || $maxAge <= 0) {
            return null;
        }
        $subdomains = (bool) ($config['include_subdomains'] ?? false);
        $preload = (bool) ($config['preload'] ?? false) && $subdomains && $maxAge >= self::PRELOAD_MIN_AGE;

        return 'max-age='.$maxAge.($subdomains ? '; includeSubDomains' : '').($preload ? '; preload' : '');
    }
}
