<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds an ETag to successful GET responses and answers matching conditional
 * requests with 304 Not Modified (audit 500 #47/#48). The body is still
 * generated, but an unchanged response skips re-transferring it — meaningful
 * bandwidth savings for large read payloads and repeated polling.
 */
final class HandleConditionalGet
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        // Only hash buffered content responses (not streamed/file downloads).
        if (! $response instanceof \Illuminate\Http\Response && ! $response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        $content = (string) $response->getContent();

        if ($content === '') {
            return $response;
        }

        $etag = '"' . md5($content) . '"';
        $response->headers->set('ETag', $etag);

        $ifNoneMatch = (string) $request->headers->get('If-None-Match', '');

        if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
            $response->setNotModified(); // 304, empties the body
        }

        return $response;
    }
}
