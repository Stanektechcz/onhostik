<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Referral attribution (audit §5k-4): a visit with `?ref=<code>` on any public page remembers the code for 30 days in a
 * plain (unencrypted, HTTP-only) cookie, so a sign-up that happens later — from the form, without the parameter — is
 * still attributed. The registration endpoint reads the cookie when the form sends no code. Nothing else reads it.
 */
final class RememberReferral
{
    public const COOKIE = 'onhost_ref';

    public const DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $ref = strtoupper(trim((string) $request->query('ref', '')));
        if ($ref !== '' && preg_match('/^[A-Z0-9]{2,8}-[A-Z0-9]{4}$/', $ref) && $request->cookie(self::COOKIE) !== $ref) {
            $response->headers->setCookie(Cookie::create(self::COOKIE, $ref, now()->addDays(self::DAYS), '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX));
        }

        return $response;
    }
}
