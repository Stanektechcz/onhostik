<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Errors\DomainError;
use Symfony\Component\HttpFoundation\Response;

/**
 * An API token reaches only what its scopes name — decided BEFORE the controller runs.
 *
 * The scope check lived inside `ApiContext::authorize()` and the command dispatch, so every endpoint that needs
 * neither was open to any token: a token minted with `invoices:read` could `PATCH /v1/me`, enrol TOTP on an account
 * without one, read the recovery codes from the answer, and then `POST /v1/auth/step-up` — clearing the step-up gate
 * for whatever high-risk action its scopes did cover. A cookie session (the portal) is not touched by this middleware.
 *
 * Deny by default: a route family that is not listed here is not available to tokens at all. The finer check by
 * permission (`assertTokenScope`) still runs afterwards.
 */
final class TokenRouteScope
{
    /** first path segment after /v1 → [scope for reads, scope for writes]; null = not available in that direction */
    private const FAMILIES = [
        'services' => ['services:read', 'services:power'],
        'invoices' => ['invoices:read', null],
        'documents' => ['invoices:read', null],
        'wallet' => ['wallet:read', null],
        'tickets' => ['tickets:write', 'tickets:write'],
        'dns' => ['dns:write', 'dns:write'],
        'zones' => ['dns:write', 'dns:write'],
        'domains' => ['domains:read', null],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        if (! $token instanceof PersonalAccessToken) {
            return $next($request); // the portal's own session
        }
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/')), fn (string $s) => $s !== ''));
        $family = ($segments[0] ?? '') === 'v1' ? ($segments[1] ?? '') : ($segments[0] ?? '');
        if ($family === 'me' && $request->isMethod('GET') && count($segments) <= 2) {
            return $next($request); // a token may ask who it is — and nothing else about the account
        }
        $pair = self::FAMILIES[$family] ?? null;
        $needed = $pair === null ? null : $pair[in_array($request->method(), ['GET', 'HEAD'], true) ? 0 : 1];
        if ($needed === null) {
            throw DomainError::forbidden('This endpoint is not available to API tokens; use the portal.');
        }
        if (! $token->can($needed)) {
            throw DomainError::forbidden("The API token lacks the {$needed} scope.");
        }

        return $next($request);
    }
}
