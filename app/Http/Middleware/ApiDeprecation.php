<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
            try {
                $deprecated = CarbonImmutable::parse((string) $rule['deprecated_at'])->timestamp;
                $sunset = ! empty($rule['sunset_at']) ? CarbonImmutable::parse((string) $rule['sunset_at'])->utc()->format('D, d M Y H:i:s').' GMT' : null;
            } catch (Throwable $e) { // a typo in configuration must not turn every API response into a 500
                Log::warning('api.deprecation_rule_invalid', ['path' => (string) $rule['path'], 'error' => $e->getMessage()]);

                continue;
            }
            $response->headers->set('Deprecation', '@'.$deprecated);
            if ($sunset !== null) {
                $response->headers->set('Sunset', $sunset);
            }
            if (! empty($rule['link'])) {
                $response->headers->set('Link', '<'.$rule['link'].'>; rel="deprecation"', false);
            }
            break;
        }

        return $response;
    }
}
