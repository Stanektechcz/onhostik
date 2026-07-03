<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security-related HTTP headers to every response.
 *
 * Headers applied:
 *   - X-Content-Type-Options: nosniff
 *   - X-Frame-Options: SAMEORIGIN
 *   - X-XSS-Protection: 1; mode=block
 *   - Referrer-Policy: strict-origin-when-cross-origin
 *   - Permissions-Policy: camera=(), microphone=(), geolocation=()
 *   - Strict-Transport-Security (HSTS) — production only (https)
 *   - Content-Security-Policy — enforced when SECURITY_CSP_ENFORCE=true
 *
 * CSP uses a per-request nonce for inline scripts so 'unsafe-inline' is
 * removed from script-src. Style-src keeps 'unsafe-inline' (CSS inline
 * styles from JS plugins are common and carry very low XSS risk).
 *
 * The nonce is shared to Blade as $cspNonce and stored on the request
 * attributes as 'csp_nonce' so non-Blade controllers can also use it.
 *
 * Enable enforce mode via: SECURITY_CSP_ENFORCE=true in .env
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a fresh nonce for this request
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        view()->share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(self)'
        );

        // HSTS — only over HTTPS (skip on local/http)
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // CSP — enforce if env flag set, otherwise report-only
        $csp = $this->buildCsp($request, $nonce);
        $cspHeader = (bool) config('app.security_csp_enforce', false)
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->headers->set($cspHeader, $csp);

        return $response;
    }

    private function buildCsp(Request $request, string $nonce): string
    {
        $self     = "'self'";
        $reverbWs = $this->reverbWsOrigin();

        // script-src: nonce + strict-dynamic (allows scripts loaded by nonce-tagged
        // scripts, which covers Livewire, Echo, jQuery plugins, etc.)
        // 'unsafe-inline' is intentionally NOT included — nonce supersedes it in
        // browsers that understand CSP3.
        $scriptSrc = implode(' ', [
            $self,
            "'nonce-{$nonce}'",
            "'strict-dynamic'",
            // CDN for Swagger UI (only needed on /api/docs)
            'https://unpkg.com',
        ]);

        return implode('; ', [
            "default-src {$self}",
            "script-src {$scriptSrc}",
            "style-src {$self} 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com https://unpkg.com",
            "font-src {$self} https://fonts.gstatic.com data:",
            "img-src {$self} data: https:",
            "connect-src {$self}" . ($reverbWs !== '' ? " {$reverbWs}" : ''),
            "frame-ancestors {$self}",
            "base-uri {$self}",
            "form-action {$self}",
        ]);
    }

    private function reverbWsOrigin(): string
    {
        $host     = (string) config('reverb.apps.apps.0.options.host', '');
        $port     = (int) config('reverb.apps.apps.0.options.port', 8080);
        $scheme   = (string) config('reverb.apps.apps.0.options.scheme', 'http');
        $wsScheme = $scheme === 'https' ? 'wss' : 'ws';

        return $host !== '' ? "{$wsScheme}://{$host}:{$port}" : '';
    }
}
