<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** The per-organization status page (audit §5j-5): the public projection by slug, and the owner's preview with the settings. */
final class OrganizationStatusController extends ApiController
{
    public function show(OrganizationStatusService $status, string $slug): JsonResponse
    {
        $organization = Organization::query()->where('slug', $slug)->first();
        if ($organization === null) {
            throw DomainError::notFound('status_page');
        }

        return response()->json(['data' => $status->build($organization)]);
    }

    /** Verifies the CNAME of the customer's own status host (audit §5k-3). */
    public function verify(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new OrganizationCommand($organization->id, $this->idempotencyKey($request, 'status_page.verify:'.now()->format('YmdHi')), ['op' => 'status_page.verify']), $this->api->context($request, $organization));
    }

    /** On-demand TLS ask: 200 when the host is a verified, enabled status host, 404 otherwise (the edge issues certificates only for 200). */
    public function hostAllowed(Request $request, OrganizationStatusService $status): JsonResponse
    {
        $host = strtolower(trim((string) $request->query('host', ''), '. '));
        if ($host === '' || strlen($host) > 253 || ! $status->hostAllowed($host)) {
            return response()->json(['error' => 'host_not_allowed', 'message' => 'No verified status page lives on this host.', 'status' => 404, 'allowed' => false, 'host' => $host], 404);
        }

        return response()->json(['allowed' => true, 'host' => $host]);
    }

    public function mine(Request $request, OrganizationStatusService $status): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $settings = OrganizationStatusService::settings([], (array) data_get($organization->settings, 'status_page', []));
        $portal = rtrim((string) config('onhost.portal_url'), '/');

        return response()->json(['data' => [
            'settings' => $settings, 'url' => "{$portal}/stav/{$organization->slug}", 'badge_url' => "{$portal}/stav/{$organization->slug}/badge.svg", 'api_url' => "{$portal}/v1/status/org/{$organization->slug}",
            'expected_cname' => OrganizationStatusService::customCname(),
            'cname_hint' => 'status.vase-domena.cz CNAME '.OrganizationStatusService::customCname().' — po ověření se stránka zobrazí i pod vaší doménou; certifikát vystaví edge automaticky (audit §5k-3).',
            'preview' => $settings['enabled'] ? $status->build($organization) : null,
        ]]);
    }
}
