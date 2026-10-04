<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * TASK-0076 (D7): the API version policy. Every API response names the version (`X-API-Version`); routes listed in
 * `onhost.api.deprecations` also carry `Deprecation` (RFC 9745, `@unix-time`), `Sunset` (RFC 8594, HTTP date) and a
 * `Link: <…>; rel="deprecation"` to the migration notes. A rule is `{path: 'v1/foo/*', deprecated_at, sunset_at?, link?}`;
 * `path` is a `Str::is` pattern against the request path.
 */
final class ApiDeprecation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-API-Version', (string) config('onhost.api.version'));
        foreach ((array) config('onhost.api.deprecations', []) as $rule) {
            if (! is_array($rule) || ! isset($rule['path'], $rule['deprecated_at']) || ! Str::is((string) $rule['path'], $request->path())) {
                continue;
            }
            $response->headers->set('Deprecation', '@'.CarbonImmutable::parse((string) $rule['deprecated_at'])->timestamp);
            if (! empty($rule['sunset_at'])) {
                $response->headers->set('Sunset', CarbonImmutable::parse((string) $rule['sunset_at'])->utc()->format('D, d M Y H:i:s').' GMT');
            }
            if (! empty($rule['link'])) {
                $response->headers->set('Link', '<'.$rule['link'].'>; rel="deprecation"', false);
            }
            break;
        }

        return $response;
    }
}
