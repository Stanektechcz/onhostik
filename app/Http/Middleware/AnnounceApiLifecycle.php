<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches version-lifecycle headers to every API response (audit 103).
 *
 * A REST API that just stops answering one day breaks integrations without
 * warning. The professional contract is to announce a deprecation in-band, well
 * ahead of the shutdown, in headers a client can act on automatically:
 *
 *   Deprecation: true          — this version is on the way out (draft-ietf httpapi)
 *   Sunset: <HTTP-date>        — the date it stops being served (RFC 8594)
 *   Link: <changelog>; rel="deprecation"
 *
 * The version is taken from the route prefix, so this middleware is added once
 * to each version group and needs no per-route wiring. Active versions get no
 * deprecation noise — only the sunset machinery, dormant until config flips.
 */
final class AnnounceApiLifecycle
{
    public function handle(Request $request, Closure $next, string $version): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $meta = config("api.versions.{$version}");

        if (! is_array($meta)) {
            return $response;
        }

        // The changelog is where a deprecation notice sends the client, so it
        // is worth advertising on every version, deprecated or not.
        $response->headers->set('Link', '<' . url('/api/changelog') . '>; rel="latest-version"', false);

        if (($meta['status'] ?? 'active') !== 'deprecated') {
            return $response;
        }

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Link', '<' . url('/api/changelog') . '>; rel="deprecation"', false);

        // RFC 8594 wants an HTTP-date. Parse the configured date as UTC so the
        // header is the same whatever timezone the server runs in — otherwise a
        // bare "2027-01-01" silently slides to the previous day on a +offset box.
        // A malformed value must not 500 a live API call, so parse defensively
        // and simply omit Sunset if it is unusable.
        $sunset = $meta['sunset'] ?? null;

        if (is_string($sunset) && $sunset !== '') {
            try {
                $date = \Illuminate\Support\Carbon::parse($sunset, 'UTC');
                $response->headers->set('Sunset', $date->toRfc7231String());
            } catch (\Throwable) {
                // leave Sunset unset
            }
        }

        return $response;
    }
}
