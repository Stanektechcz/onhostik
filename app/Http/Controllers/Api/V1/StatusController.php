<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Incidents\IncidentService;
use Onhost\Domain\Incidents\IncidentStateMachine;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\SlaCredit;
use Onhost\Domain\Incidents\Presenters;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Platform\Errors\DomainError;

/** Public status page (`/stav`) and the customer's own incident/SLA view (`/panel`). */
final class StatusController extends ApiController
{
    public function status(IncidentService $incidents): JsonResponse
    {
        return response()->json(['data' => $incidents->publicStatus()]);
    }

    public function incidents(Request $request, IncidentService $incidents): JsonResponse
    {
        $query = Incident::query()->where('visibility', 'public')->orderByDesc('started_at');
        if ($request->boolean('open')) {
            $query->whereNotIn('state', [IncidentStateMachine::RESOLVED, IncidentStateMachine::POSTMORTEM]);
        }
        $limit = min(100, max(1, (int) $request->query('limit', 25)));
        $items = $query->limit($limit)->offset(max(0, (int) $request->query('offset', 0)))->get();

        return response()->json(['data' => $items->map(fn (Incident $i) => $incidents->publicIncident($i))->values()->all()])->header('X-Total-Count', (string) $query->count());
    }

    public function incident(string $number, IncidentService $incidents): JsonResponse
    {
        $incident = Incident::query()->where('number', strtoupper($number))->where('visibility', 'public')->first();
        if ($incident === null) {
            throw DomainError::notFound('incident');
        }

        return response()->json(['data' => $incidents->publicIncident($incident)]);
    }

    /** Published post-mortems only. */
    public function postmortems(IncidentService $incidents): JsonResponse
    {
        $items = Incident::query()->where('visibility', 'public')->where('state', IncidentStateMachine::POSTMORTEM)->orderByDesc('resolved_at')->limit(50)->get()
            ->filter(fn (Incident $i) => $i->postmortem['published'] ?? false);

        return response()->json(['data' => $items->map(fn (Incident $i) => $incidents->publicIncident($i))->values()->all()]);
    }

    /** Incidents affecting the calling organization's services (any visibility, public notes only). */
    public function mine(Request $request, IncidentService $incidents): JsonResponse
    {
        $organization = $this->api->organization($request);
        $query = Incident::query()->whereJsonContains('affected_organizations', $organization->id)->orderByDesc('started_at');

        return $this->api->paginate($request, $query, fn (Incident $i) => $incidents->publicIncident($i), 'started_at');
    }

    public function credits(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $query = SlaCredit::query()->where('organization_id', $organization->id)->whereIn('state', ['approved', 'issued']);

        return $this->api->paginate($request, $query, fn (SlaCredit $c) => array_diff_key(Presenters::credit($c), ['calculation' => 1, 'approved_by' => 1]) + ['incident' => $c->calculation['incident'] ?? null]);
    }

    /** External probe agents report results with their probe token (no user session). */
    public function ingest(Request $request, SlaService $sla): JsonResponse
    {
        $probe = $sla->authenticateProbe($request->bearerToken());
        if ($probe === null) {
            throw new DomainError('probe_unauthorized', 'Unknown probe token.', 401);
        }
        $data = $request->validate([
            'results' => ['required', 'array', 'min:1', 'max:500'],
            'results.*.at' => ['required', 'date'],
            'results.*.ok' => ['required', 'boolean'],
            'results.*.latency_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'results.*.detail' => ['nullable', 'string', 'max:250'],
        ]);

        return response()->json(['data' => $sla->ingest($probe, $data['results'])], 202);
    }
}
