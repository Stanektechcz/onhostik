<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Console relay hand-off (blueprint §41: customers never receive provider credentials).
 * `POST /v1/services/{id}/console-token` stores a single-use `con_…` descriptor in the cache
 * (Proxmox: node/vmid/vncticket; Pterodactyl: wings socket + token). The websocket relay service
 * resolves it here with its shared key, the descriptor is consumed on first read, and the relay
 * then proxies noVNC / xterm frames to the upstream. Browsers never call this endpoint directly.
 */
final class ConsoleRelayController extends Controller
{
    public function __construct(private readonly Cache $cache, private readonly AuditRecorder $audit) {}

    public function resolve(Request $request, string $token): JsonResponse
    {
        $relayKey = (string) config('onhost.console.relay_key', '');
        $provided = (string) $request->header('X-Relay-Key', '');
        if ($relayKey === '' || ! hash_equals($relayKey, $provided)) {
            throw new DomainError('relay_unauthorized', 'Console relay key required.', 401);
        }
        if (! preg_match('/^con_[0-9a-z]{26}$/', $token)) {
            throw new DomainError('console_token_invalid', 'Malformed console token.', 422);
        }
        $descriptor = $this->cache->pull("onhost:console:{$token}");
        if (! is_array($descriptor)) {
            throw new DomainError('console_token_expired', 'Console token is unknown, expired or already used.', 410);
        }
        $this->audit->record(CommandContext::system('console.relay'), 'service.console.relay', 'succeeded', ['kind' => $descriptor['kind'] ?? null, 'service_id' => $descriptor['service_id'] ?? null, 'relay_ip' => $request->ip()], 'service', $descriptor['service_id'] ?? null);

        return response()->json(['data' => $descriptor + ['single_use' => true, 'resolved_at' => now()->toIso8601String()]]);
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
        $organizationId = $descriptor['organization_id'] ?? null;
        $member = $organizationId === null || OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $organizationId)->where('state', 'active')->exists() || $user->is_staff;

        return response()->json(['data' => ['valid' => (bool) $member, 'kind' => $descriptor['kind'] ?? null, 'expires_at' => $descriptor['expires_at'] ?? null]]);
    }
}
