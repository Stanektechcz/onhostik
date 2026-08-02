<?php

declare(strict_types=1);

namespace App\Domains\Api\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * RFC 7807 "problem+json" error bodies for the API (audit 500 #206).
 *
 * One predictable error shape everywhere beats a different {"error": "..."}
 * per controller: clients can branch on `type`/`status` instead of parsing
 * prose. The `type` is a stable URI slug, `detail` the human message, and
 * validation failures carry a machine-readable `errors` map.
 *
 * Never leaks internals: in production an unhandled exception becomes a generic
 * 500 problem, with the request id attached so support can correlate it.
 */
final class ProblemDetails
{
    /** Map an exception to a problem+json response. */
    public static function fromThrowable(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return self::make(
                status: 422,
                type: 'validation-failed',
                title: 'Neplatná data požadavku',
                detail: 'Požadavek neprošel validací; opravte uvedená pole.',
                request: $request,
                extra: ['errors' => $e->errors()],
            );
        }

        // These carry their HTTP meaning implicitly rather than via
        // HttpExceptionInterface, so map them before falling back to 500.
        $status = match (true) {
            $e instanceof AuthenticationException  => 401,
            $e instanceof AuthorizationException   => 403,
            $e instanceof ModelNotFoundException   => 404,
            $e instanceof HttpExceptionInterface   => $e->getStatusCode(),
            default                                => 500,
        };
        $safe   = $status < 500 || config('app.debug') === true;

        $response = self::make(
            status: $status,
            type: self::typeForStatus($status),
            title: self::titleForStatus($status),
            detail: $safe && $e->getMessage() !== '' ? $e->getMessage() : self::titleForStatus($status),
            request: $request,
        );

        // Preserve headers the framework set (Retry-After, X-RateLimit-*,
        // WWW-Authenticate…). Throttle headers arrive as ints, which the
        // header bag refuses — cast to string.
        if ($e instanceof HttpExceptionInterface) {
            foreach ($e->getHeaders() as $key => $value) {
                $response->headers->set($key, is_array($value) ? $value : (string) $value);
            }
        }

        return $response;
    }

    /**
     * Build a problem+json response.
     *
     * @param array<string, mixed> $extra
     */
    public static function make(
        int $status,
        string $type,
        string $title,
        string $detail,
        ?Request $request = null,
        array $extra = [],
    ): JsonResponse {
        $body = [
            'type'   => 'https://onhost.cz/problems/' . $type,
            'title'  => $title,
            'status' => $status,
            'detail' => $detail,
            // Kept alongside `detail` so existing clients reading Laravel's
            // conventional `message` keep working — a compatible superset.
            'message' => $detail,
        ];

        if ($request !== null) {
            $body['instance']   = '/' . ltrim($request->path(), '/');
            $requestId          = $request->attributes->get('request_id');
            $body['request_id'] = is_string($requestId) ? $requestId : null;
        }

        return response()
            ->json(array_merge($body, $extra), $status)
            ->header('Content-Type', 'application/problem+json');
    }

    private static function typeForStatus(int $status): string
    {
        return match ($status) {
            400     => 'bad-request',
            401     => 'unauthenticated',
            403     => 'forbidden',
            404     => 'not-found',
            405     => 'method-not-allowed',
            409     => 'conflict',
            422     => 'validation-failed',
            429     => 'rate-limit-exceeded',
            503     => 'service-unavailable',
            default => $status >= 500 ? 'server-error' : 'request-error',
        };
    }

    private static function titleForStatus(int $status): string
    {
        return match ($status) {
            400     => 'Chybný požadavek',
            401     => 'Vyžadováno přihlášení',
            403     => 'Přístup odepřen',
            404     => 'Nenalezeno',
            405     => 'Metoda není povolena',
            409     => 'Konflikt',
            422     => 'Neplatná data požadavku',
            429     => 'Překročen limit požadavků',
            503     => 'Služba dočasně nedostupná',
            default => $status >= 500 ? 'Vnitřní chyba serveru' : 'Chyba požadavku',
        };
    }
}
