<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application locale for validation messages and domain errors: `?locale=` first, then the browser's Accept-Language
 * (Czech and Slovak visitors get Czech messages, everybody else English). Requests without a language header keep
 * the configured default so API clients see stable English messages.
 */
final class SetLocale
{
    private const SUPPORTED = ['cs', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $wanted = $request->query('locale');
        if (! is_string($wanted) || ! in_array($wanted, self::SUPPORTED, true)) {
            $wanted = null;
            if (trim((string) $request->headers->get('Accept-Language', '')) !== '') {
                $preferred = (string) $request->getPreferredLanguage(['cs', 'sk', 'en']);
                $wanted = $preferred === 'sk' ? 'cs' : $preferred;
            }
        }
        if (is_string($wanted) && in_array($wanted, self::SUPPORTED, true)) {
            app()->setLocale($wanted);
        }

        return $next($request);
    }
}
