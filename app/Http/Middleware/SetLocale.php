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
        $locale = $request->session()->get('locale')
            ?? $request->user()->locale
            ?? config('app.locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
