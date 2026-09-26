<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
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
 * permission (`assertTokenScope`, the one map in TokenScopes) still runs afterwards. A service's console token is its own
 * scope already here: a console is not a read and not a restart (C13-H2c), so neither `services:read` (GET) nor
 * `services:power` (POST) reaches it. `POST services/{id}/actions` is decided by the action's own permission (the same
 * ServiceActionCommand::permissionFor the bus asks), so the console's commands take `services:console` alone.
 */
final class TokenRouteScope
{
    /** first path segment after /v1 → [scope for reads, scope for writes]; null = not available in that direction */
    private const FAMILIES = [
        'services' => [TokenScopes::SERVICES_READ, TokenScopes::SERVICES_POWER],
        'invoices' => [TokenScopes::INVOICES_READ, null],
        'documents' => [TokenScopes::INVOICES_READ, null],
        'wallet' => [TokenScopes::WALLET_READ, null],
        'tickets' => [TokenScopes::TICKETS_WRITE, TokenScopes::TICKETS_WRITE],
        'dns' => [TokenScopes::DNS_WRITE, TokenScopes::DNS_WRITE],
        'zones' => [TokenScopes::DNS_WRITE, TokenScopes::DNS_WRITE],
        'domains' => [TokenScopes::DOMAINS_READ, null],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $token = TokenScopes::tokenOf($request->user());
        if ($token === null) {
            return $next($request); // the portal's own session
        }
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/')), fn (string $s) => $s !== ''));
        $family = ($segments[0] ?? '') === 'v1' ? ($segments[1] ?? '') : ($segments[0] ?? '');
        if ($family === 'me' && $request->isMethod('GET') && count($segments) <= 2) {
            return $next($request); // a token may ask who it is — and nothing else about the account
        }
        $sub = $family === 'services' ? ($segments[($segments[0] ?? '') === 'v1' ? 3 : 2] ?? '') : '';
        if ($sub === 'access') {
            throw DomainError::forbidden('Sharing a service is not available to API tokens; use the portal.'); // a token that may restart a service must not be able to let somebody in
        }
        $pair = self::FAMILIES[$family] ?? null;
        $needed = $pair === null ? null : $pair[in_array($request->method(), ['GET', 'HEAD'], true) ? 0 : 1];
        if ($sub === 'console-token') {
            $needed = TokenScopes::SERVICES_CONSOLE; // a console is neither a read nor a restart: GET and POST alike (C13-H2c)
        }
        if ($sub === 'actions' && $request->isMethod('POST') && count($segments) === (($segments[0] ?? '') === 'v1' ? 4 : 3)) {
            // the generic action endpoint is decided by what the action is, through the same map the bus uses: the console's
            // commands need services:console and nothing else, a restart services:power (TASK-0030 review round 1 — a
            // console-only token was told it lacked services:power on the very commands its scope exists for). An unknown word
            // keeps services:power and meets the controller's validator, the answer it always had. The params go along: a
            // schedule with a `command` task is the console here too, not only at the dispatch (TASK-0030 LOW, closed at the
            // stack polish — without them a console-only token was refused a console schedule, and a power-only one passed)
            $action = $request->input('action');
            if (is_string($action) && in_array($action, ServiceActionWorkflow::ACTIONS, true)) {
                $needed = TokenScopes::for(ServiceActionCommand::permissionFor($action, (array) $request->input('params', [])));
            }
            if ($needed === null) {
                throw DomainError::forbidden('This action is not available to API tokens; use the portal.');
            }
        }
        if ($needed === null) {
            throw DomainError::forbidden('This endpoint is not available to API tokens; use the portal.');
        }
        if (! $token->can($needed)) {
            throw DomainError::forbidden("The API token lacks the {$needed} scope.");
        }

        return $next($request);
    }
}
