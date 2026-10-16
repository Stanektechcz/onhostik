<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application locale for validation messages and domain errors: `?locale=` first, then the browser's Accept-Language.
 * Czech visitors get Czech messages, everybody else English. Slovak visitors get Czech too, unless the switch
 * `config('onhost.locale_sk')` (ONHOST_LOCALE_SK, default off, W4-4) is on: then `sk` is a supported locale for both
 * `?locale=` and Accept-Language, and lang/sk falls back to Czech (AppServiceProvider). Requests without a language
 * header keep the configured default so API clients see stable English messages.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = self::supported();
        $wanted = $request->query('locale');
        if (! is_string($wanted) || ! in_array($wanted, $supported, true)) {
            $wanted = null;
            if (trim((string) $request->headers->get('Accept-Language', '')) !== '') {
                $preferred = (string) $request->getPreferredLanguage(['cs', 'sk', 'en']);
                $wanted = $preferred === 'sk' && ! in_array('sk', $supported, true) ? 'cs' : $preferred;
            }
        }
        // W6-6: the Slovak public site lives under /sk/ on the same domain; the prefix wins, but only while the switch is on
        if (in_array('sk', $supported, true) && ($request->path() === 'sk' || str_starts_with($request->path(), 'sk/'))) {
            $wanted = 'sk';
        }
        if (is_string($wanted) && in_array($wanted, $supported, true)) {
            app()->setLocale($wanted);
        }

        return $next($request);
    }

    /** @return list<string> */
    public static function supported(): array
    {
        return (bool) config('onhost.locale_sk', false) ? ['cs', 'en', 'sk'] : ['cs', 'en'];
    }
}
