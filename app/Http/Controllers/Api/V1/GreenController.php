<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Green hosting (audit §5j-10): the platform's energy profile (public) and the organization's estimated footprint. */
final class GreenController extends ApiController
{
    public function platform(GreenService $green): JsonResponse
    {
        return response()->json(['data' => $green->platform()]);
    }

    /** PDU / IPMI probes report measured watts per node (audit §5k-6); the same probe tokens as the SLA probes. */
    public function ingestPower(Request $request, GreenService $green, SlaService $sla): JsonResponse
    {
        if ($sla->authenticateProbe($request->bearerToken()) === null) {
            throw new DomainError('probe_unauthorized', 'Unknown probe token.', 401);
        }
        $data = $request->validate(['readings' => ['required', 'array', 'min:1', 'max:500'], 'readings.*.node' => ['required', 'string', 'max:80'], 'readings.*.watts' => ['required', 'numeric', 'min:0', 'max:100000'], 'readings.*.at' => ['nullable', 'date'], 'readings.*.vms' => ['nullable', 'array', 'max:500'], 'readings.*.vms.*.id' => ['required', 'string', 'max:80'], 'readings.*.vms.*.watts' => ['required', 'numeric', 'min:0', 'max:10000']]);

        return response()->json(['data' => $green->ingest($data['readings'])], 202);
    }

    public function account(Request $request, GreenService $green): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $footprint = $green->footprint($organization);

        return response()->json(['data' => $footprint + ['badge_url' => rtrim((string) config('onhost.portal_url'), '/').'/green/badge.svg?pct='.$footprint['renewable_pct']]]);
    }
}
