<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Integrations\ActionHookService;
use Onhost\Domain\Integrations\Commands\IntegrationCommand;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Customer integrations: the Discord account link (slash commands, confirmation buttons) and action hooks (signed
 * URLs that run one predefined service action). The two public endpoints — Discord interactions and hook triggers —
 * authenticate by signature and token respectively.
 */
final class IntegrationController extends ApiController
{
    // ── Discord ────────────────────────────────────────────────────────────────────────────────────────

    public function discord(Request $request, DiscordService $discord): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));

        return $this->ok($discord->status($organization));
    }

    public function discordLinkCode(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new IntegrationCommand($organization->id, $this->idempotencyKey($request, 'discord.link_code'), ['op' => 'discord.link_code']), $this->api->context($request, $organization), 201);
    }

    public function discordUnlink(Request $request, string $link): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new IntegrationCommand($organization->id, $this->idempotencyKey($request, 'discord.unlink'), ['op' => 'discord.unlink', 'params' => ['link_id' => $link]]), $this->api->context($request, $organization));
    }

    /** Public: Discord posts every slash command and button press here, signed with the application's key. */
    public function discordInteractions(Request $request, DiscordService $discord): JsonResponse
    {
        $signature = (string) $request->header('X-Signature-Ed25519', '');
        $timestamp = (string) $request->header('X-Signature-Timestamp', '');
        $body = (string) $request->getContent();
        if (! $discord->verifySignature($signature, $timestamp, $body)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }
        $payload = json_decode($body, true);

        return response()->json($discord->handleInteraction(is_array($payload) ? $payload : []));
    }

    // ── action hooks ───────────────────────────────────────────────────────────────────────────────────

    public function hooks(Request $request, ActionHookService $hooks): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'service.read', CommandScope::organization($organization->id));
        $service = null;
        if ($request->query('service')) {
            $service = Service::query()->where('organization_id', $organization->id)->find((string) $request->query('service'));
            if ($service === null) {
                throw DomainError::notFound('service');
            }
        }

        return $this->ok(['hooks' => $hooks->list($organization, $service), 'actions' => ActionHookService::ALLOWED]);
    }

    public function createHook(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['service_id' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:80'], 'action' => ['required', 'string', 'max:40'], 'params' => ['nullable', 'array']]);

        return $this->dispatch(new IntegrationCommand($organization->id, $this->idempotencyKey($request, 'hook.create'), ['op' => 'hook.create', 'params' => $data]), $this->api->context($request, $organization), 201);
    }

    public function deleteHook(Request $request, string $hook): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new IntegrationCommand($organization->id, $this->idempotencyKey($request, 'hook.delete'), ['op' => 'hook.delete', 'params' => ['hook_id' => $hook]]), $this->api->context($request, $organization));
    }

    /** Public: run the hook's action; the token in the URL is the credential. */
    public function runHook(Request $request, ActionHookService $hooks, string $token): JsonResponse
    {
        $result = $hooks->trigger($token, $request->ip());

        return response()->json($result, $result['accepted'] ? 202 : 200);
    }
}
