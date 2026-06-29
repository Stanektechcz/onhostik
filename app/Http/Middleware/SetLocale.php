<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the session-selected locale (set via the locale.switch route).
 * Falls back to the authenticated user's stored locale, then config default.
 */
class SetLocale
{
    private const SUPPORTED = ['cs', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')    // 1. session (locale.switch)
            ?? $request->user()?->locale                 // 2. user profile (null-safe)
            ?? config('app.locale', 'cs');               // 3. config default

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        } else {
            app()->setLocale('cs');
        }

        return $next($request);
    }
}
