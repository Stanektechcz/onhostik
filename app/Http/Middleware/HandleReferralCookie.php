<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Partner\Services\ReferralTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web middleware that captures ?ref=CODE from the query string,
 * stores it in a cookie and session, and creates a visitor-level
 * referral record. Must never throw or break page loads.
 */
class HandleReferralCookie
{
    public function __construct(private ReferralTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $this->resolveCode($request);

        if ($code !== null) {
            $this->tracker->handleVisit($request, $code);

            /** @var Response $response */
            $response = $next($request);

            // Refresh cookie on every qualifying request to extend TTL
            $response->cookie(
                config('partner.cookie_name'),
                $code,
                config('partner.cookie_days') * 24 * 60, // minutes
                '/',
                null,
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: 'Lax',
            );

            return $response;
        }

        return $next($request);
    }

    private function resolveCode(Request $request): ?string
    {
        // 1. Prefer explicit ?ref= query parameter (sets/resets cookie)
        $fromQuery = $request->query('ref');
        if (is_string($fromQuery) && $fromQuery !== '') {
            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $fromQuery));
        }

        // 2. Fall back to existing cookie
        $fromCookie = $request->cookie(config('partner.cookie_name'));
        if (is_string($fromCookie) && $fromCookie !== '') {
            // Restore session key if it got lost (e.g. after session regen)
            if (session(config('partner.session_key')) === null) {
                session([config('partner.session_key') => $fromCookie]);
            }

            return null; // Cookie already set — no need to re-set
        }

        return null;
    }
}
