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
 *   - Content-Security-Policy — report-only by default (not enforced yet)
 *
 * CSP is intentionally in report-only mode until a full nonce / hash
 * audit of inline scripts is done. Toggle via SECURITY_CSP_ENFORCE=true.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
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
        $csp = $this->buildCsp($request);
        $cspHeader = (bool) config('app.security_csp_enforce', false)
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->headers->set($cspHeader, $csp);

        return $response;
    }

    private function buildCsp(Request $request): string
    {
        $self     = "'self'";
        $appUrl   = (string) config('app.url', '');
        $reverbWs = $this->reverbWsOrigin();

        return implode('; ', [
            "default-src {$self}",
            "script-src {$self} 'unsafe-inline' https://fonts.googleapis.com",
            "style-src {$self} 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com",
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
        $host   = (string) config('reverb.apps.apps.0.options.host', '');
        $port   = (int) config('reverb.apps.apps.0.options.port', 8080);
        $scheme = (string) config('reverb.apps.apps.0.options.scheme', 'http');
        $wsScheme = $scheme === 'https' ? 'wss' : 'ws';

        return $host !== '' ? "{$wsScheme}://{$host}:{$port}" : '';
    }
}
