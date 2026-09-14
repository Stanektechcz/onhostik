<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Incidents\Commands\IncidentCommand;
use Onhost\Domain\Incidents\IncidentService;
use Onhost\Domain\Incidents\IncidentStateMachine;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\SlaCredit;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Incidents\Presenters;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Admin `#/incidenty`: incidents, maintenance windows, probes, SLO dashboard, SLA credits. */
final class IncidentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'incident.manage', CommandScope::global());
        $query = Incident::query();
        foreach (['state', 'severity', 'visibility', 'source'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }
        if ($request->boolean('open')) {
            $query->whereNotIn('state', [IncidentStateMachine::RESOLVED, IncidentStateMachine::POSTMORTEM]);
        }

        return $this->api->paginate($request, $query, fn (Incident $i) => Presenters::incident($i, true), 'started_at');
    }

    public function show(Request $request, string $incident): JsonResponse
    {
        $this->api->authorize($request, 'incident.manage', CommandScope::global());

        return $this->ok(Presenters::incident($this->find($incident), true) + ['flows' => IncidentStateMachine::machine()->toArray()]);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'], 'severity' => ['required', 'in:p1,p2,p3,p4'], 'components' => ['required', 'array', 'min:1'], 'components.*' => ['string', 'max:40'],
            'impact' => ['nullable', 'string', 'max:2000'], 'visibility' => ['nullable', 'in:public,internal'], 'security' => ['nullable', 'boolean'], 'affected_services' => ['nullable', 'array'], 'affected_services.*' => ['string'],
            'started_at' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:2000'], 'sla_relevant' => ['nullable', 'boolean'],
        ]);

        return $this->dispatch(new IncidentCommand($this->idempotencyKey($request, 'incident.open'), ['op' => 'open'] + $data), $this->api->context($request), 201);
    }

    public function update(Request $request, string $incident): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:4000'], 'state' => ['nullable', 'string', 'max:20'], 'public' => ['nullable', 'boolean']]);

        return $this->dispatch(new IncidentCommand($this->idempotencyKey($request, "incident.update:{$incident}"), ['op' => 'update', 'incident_id' => $incident] + $data), $this->api->context($request));
    }

    public function resolve(Request $request, string $incident): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:4000']]);

        return $this->dispatch(new IncidentCommand("incident.resolve:{$incident}", ['op' => 'resolve', 'incident_id' => $incident, 'note' => $data['note'] ?? ''] + $data), $this->api->context($request));
    }

    public function postmortem(Request $request, string $incident): JsonResponse
    {
        $data = $request->validate([
            'summary' => ['required', 'string', 'max:8000'], 'root_cause' => ['required', 'string', 'max:8000'], 'timeline' => ['nullable', 'array'], 'timeline.*.at' => ['required', 'date'], 'timeline.*.what' => ['required', 'string', 'max:500'],
            'actions' => ['nullable', 'array'], 'actions.*.owner' => ['required', 'string', 'max:120'], 'actions.*.deadline' => ['required', 'date'], 'actions.*.what' => ['required', 'string', 'max:500'], 'actions.*.verified' => ['nullable', 'boolean'], 'publish' => ['nullable', 'boolean'],
        ]);

        return $this->dispatch(new IncidentCommand("incident.postmortem:{$incident}", ['op' => 'postmortem', 'incident_id' => $incident] + $data), $this->api->context($request));
    }

    public function metrics(Request $request, IncidentService $incidents): JsonResponse
    {
        $this->api->authorize($request, 'report.read', CommandScope::global());
        $from = $request->filled('from') ? now()->parse((string) $request->query('from')) : now()->subDays(30);
        $to = $request->filled('to') ? now()->parse((string) $request->query('to')) : now();

        return $this->ok($incidents->metrics($from, $to));
    }

    public function components(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'incident.manage', CommandScope::global());

        return $this->ok(StatusComponent::query()->orderBy('sort')->get()->map(fn (StatusComponent $c) => $c->toArray())->all());
    }

    // ── maintenance ─────────────────────────────────────────────────────────

    public function maintenances(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'maintenance.manage', CommandScope::global());
        $query = Maintenance::query();
        if ($request->filled('state')) {
            $query->where('state', (string) $request->query('state'));
        }

        return $this->api->paginate($request, $query, fn (Maintenance $m) => Presenters::maintenance($m), 'starts_at');
    }

    public function scheduleMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'], 'components' => ['required', 'array', 'min:1'], 'components.*' => ['string', 'max:40'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'impact' => ['nullable', 'string', 'max:2000'], 'rollback' => ['required', 'string', 'max:4000'], 'affected_services' => ['nullable', 'array'], 'affected_services.*' => ['string'], 'change_ticket' => ['nullable', 'string', 'max:40'],
            'sla_treatment' => ['nullable', 'in:excluded,counted'], 'emergency' => ['nullable', 'boolean'],
        ]);

        return $this->dispatch(new IncidentCommand($this->idempotencyKey($request, 'maintenance.schedule'), ['op' => 'maintenance.schedule'] + $data), $this->api->context($request), 201);
    }

    public function approveMaintenance(Request $request, string $maintenance): JsonResponse
    {
        return $this->dispatch(new IncidentCommand("maintenance.approve:{$maintenance}", ['op' => 'maintenance.approve', 'maintenance_id' => $maintenance]), $this->api->context($request));
    }

    public function cancelMaintenance(Request $request, string $maintenance): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250']]);

        return $this->dispatch(new IncidentCommand("maintenance.cancel:{$maintenance}", ['op' => 'maintenance.cancel', 'maintenance_id' => $maintenance] + $data), $this->api->context($request, null, $data['reason']));
    }

    public function completeMaintenance(Request $request, string $maintenance): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        return $this->dispatch(new IncidentCommand("maintenance.complete:{$maintenance}", ['op' => 'maintenance.complete', 'maintenance_id' => $maintenance] + $data), $this->api->context($request));
    }

    // ── probes & SLO ─────────────────────────────────────────────────────────

    public function probes(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'incident.manage', CommandScope::global());

        return $this->api->paginate($request, SlaProbe::query(), fn (SlaProbe $p) => Presenters::probe($p), 'key');
    }

    public function registerProbe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/'], 'component_key' => ['required', 'string', 'max:40'], 'kind' => ['required', 'in:http,dns,tcp,icmp'], 'target' => ['required', 'string', 'max:250'],
            'location' => ['required', 'string', 'max:40'], 'expected' => ['nullable', 'array'], 'interval_seconds' => ['nullable', 'integer', 'min:15', 'max:3600'],
        ]);

        return $this->dispatch(new IncidentCommand("probe.register:{$data['key']}:".now()->timestamp, ['op' => 'probe.register'] + $data), $this->api->context($request), 201);
    }

    public function slo(Request $request, SlaService $sla): JsonResponse
    {
        $this->api->authorize($request, 'report.read', CommandScope::global());

        return $this->ok($sla->report());
    }

    // ── SLA credits ──────────────────────────────────────────────────────────

    public function credits(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'sla.credit.manage', CommandScope::global());
        $query = SlaCredit::query();
        foreach (['state', 'incident_id', 'organization_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }

        return $this->api->paginate($request, $query, fn (SlaCredit $c) => Presenters::credit($c));
    }

    public function creditCandidates(Request $request, string $incident): JsonResponse
    {
        return $this->dispatch(new IncidentCommand("credit.candidates:{$incident}:".now()->timestamp, ['op' => 'credit.candidates', 'incident_id' => $incident]), $this->api->context($request));
    }

    public function approveCredit(Request $request, string $credit): JsonResponse
    {
        return $this->dispatch(new IncidentCommand("credit.approve:{$credit}", ['op' => 'credit.approve', 'credit_id' => $credit]), $this->api->context($request));
    }

    public function rejectCredit(Request $request, string $credit): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->dispatch(new IncidentCommand("credit.reject:{$credit}", ['op' => 'credit.reject', 'credit_id' => $credit] + $data), $this->api->context($request, null, $data['reason']));
    }

    public function issueCredit(Request $request, string $credit): JsonResponse
    {
        return $this->dispatch(new IncidentCommand("credit.issue:{$credit}", ['op' => 'credit.issue', 'credit_id' => $credit]), $this->api->context($request));
    }

    private function find(string $id): Incident
    {
        $incident = Incident::query()->find($id) ?? Incident::query()->where('number', strtoupper($id))->first();
        if ($incident === null) {
            throw DomainError::notFound('incident');
        }

        return $incident;
    }
}
