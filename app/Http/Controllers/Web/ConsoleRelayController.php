<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Services\Console\ConsoleSessions;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Console relay hand-off (blueprint §41: customers never receive provider credentials).
 * `POST /v1/services/{id}/console-token` stores a single-use `con_…` descriptor in the cache
 * (Proxmox: node/vmid/vncticket; Pterodactyl: wings socket + token). The websocket relay service
 * resolves it here with its shared key, the descriptor is consumed on first read, and the relay
 * then proxies noVNC / xterm frames to the upstream. Browsers never call this endpoint directly.
 *
 * TASK-0044 (D20, S1-08): the descriptor is no bearer value any more. Resolving it asks the person it was issued to whether they
 * may still open that console (ConsoleSessions::refusal — account active, `service.console` still held, not ended since), and the
 * relay asks again every ConsoleSessions::aliveSeconds() at `GET /console/ws/{token}/alive`: a "no" (410) closes the socket. A
 * member removed mid-session, an owner taken out by a recovery, a person whose MFA support reset: their console ends within it.
 */
final class ConsoleRelayController extends Controller
{
    public function __construct(private readonly Cache $cache, private readonly AuditRecorder $audit, private readonly ConsoleSessions $sessions) {}

    public function resolve(Request $request, string $token): JsonResponse
    {
        $this->assertRelay($request, $token);
        $descriptor = $this->cache->pull("onhost:console:{$token}");
        if (! is_array($descriptor)) {
            throw new DomainError('console_token_expired', 'Console token is unknown, expired or already used.', 410);
        }
        // TASK-0044 (S1-08): the person it was issued to, asked now — a ticket issued a minute before their removal opens nothing
        $refusal = $this->sessions->refusal($descriptor);
        if ($refusal !== null) {
            $this->audit->record(CommandContext::system('console.relay'), 'service.console.relay', 'denied', ['kind' => $descriptor['kind'] ?? null, 'service_id' => $descriptor['service_id'] ?? null, 'reason' => $refusal, 'relay_ip' => $request->ip()], 'service', $descriptor['service_id'] ?? null);

            throw new DomainError('console_session_ended', 'The person this console was issued to may no longer open it.', 410, ['reason' => $refusal]);
        }
        $this->sessions->opened($token, $descriptor);
        $this->audit->record(CommandContext::system('console.relay'), 'service.console.relay', 'succeeded', ['kind' => $descriptor['kind'] ?? null, 'service_id' => $descriptor['service_id'] ?? null, 'relay_ip' => $request->ip()], 'service', $descriptor['service_id'] ?? null);

        return response()->json(['data' => $descriptor + ['single_use' => true, 'resolved_at' => now()->toIso8601String(), 'alive_every' => ConsoleSessions::aliveSeconds()]]);
    }

    /**
     * TASK-0044 (D20, S1-08): the relay asks every `alive_every` seconds whether the console it resolved may stay open. 200 = keep
     * it; 410 = close it (the person was removed, lost the console, was ended by SessionKill, or the service is gone).
     */
    public function alive(Request $request, string $token): JsonResponse
    {
        $this->assertRelay($request, $token);
        $refusal = $this->sessions->alive($token);
        if ($refusal !== null) {
            if ($refusal !== 'session_unknown') {
                $this->audit->record(CommandContext::system('console.relay'), 'service.console.closed', 'succeeded', ['reason' => $refusal, 'relay_ip' => $request->ip()], 'service', null);
            }

            throw new DomainError('console_session_ended', 'This console may no longer stay open.', 410, ['reason' => $refusal]);
        }

        return response()->json(['data' => ['alive' => true, 'next_in' => ConsoleSessions::aliveSeconds()]]);
    }

    private function assertRelay(Request $request, string $token): void
    {
        $relayKey = (string) config('onhost.console.relay_key', '');
        $provided = (string) $request->header('X-Relay-Key', '');
        if ($relayKey === '' || ! hash_equals($relayKey, $provided)) {
            throw new DomainError('relay_unauthorized', 'Console relay key required.', 401);
        }
        if (! preg_match('/^con_[0-9a-z]{26}$/', $token)) {
            throw new DomainError('console_token_invalid', 'Malformed console token.', 422);
        }
    }

    /** Browser-side pre-flight: is the token still valid for the signed-in user (no upstream detail is revealed). */
    public function check(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            throw new DomainError('unauthenticated', 'Sign in first.', 401);
        }
        $descriptor = $this->cache->get("onhost:console:{$token}");
        if (! is_array($descriptor)) {
            return response()->json(['data' => ['valid' => false]]);
        }
        // Whose console it is comes from the SERVICE the token was issued for. The adapters never wrote an organization into the
        // descriptor, so this check read `null` and let every signed-in user through; a token with no known owner is nobody's.
        $organizationId = $descriptor['organization_id'] ?? (isset($descriptor['service_id']) ? Service::query()->whereKey((string) $descriptor['service_id'])->value('organization_id') : null);
        // TASK-0039 (IF-8, audit SS-14): any member of staff passed here, whoever the console was for. Now the person the token was
        // issued to (ServiceService::consoleAccess records them, staff included) and the organization's current members only
        $issuedTo = $descriptor['issued_to'] ?? null;
        $member = (is_string($issuedTo) && $issuedTo !== '' && hash_equals($issuedTo, (string) $user->getAuthIdentifier()))
            || ($organizationId !== null && OrganizationMembership::query()->where('user_id', $user->getAuthIdentifier())->where('organization_id', $organizationId)->current()->exists());

        // TASK-0044: and the console may still run for the person it was issued to (removed, ended by SessionKill)
        return response()->json(['data' => ['valid' => $member && $this->sessions->refusal($descriptor) === null, 'kind' => $descriptor['kind'] ?? null, 'expires_at' => $descriptor['expires_at'] ?? null]]);
    }
}
