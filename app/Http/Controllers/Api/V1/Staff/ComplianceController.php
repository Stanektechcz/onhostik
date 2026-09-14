<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Compliance\Commands\ComplianceCommand;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\ComplianceTimer;
use Onhost\Domain\Compliance\Models\CyberIncident;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Incidents\Presenters;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Admin `#/incidenty` security tab: cyber incidents, regulatory timers, DSA abuse cases, data requests, legal hold. */
final class ComplianceController extends ApiController
{
    public function cyberIncidents(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'security.incident.manage', CommandScope::global());

        return $this->api->paginate($request, CyberIncident::query()->when($request->filled('state'), fn ($q) => $q->where('state', (string) $request->query('state'))), fn (CyberIncident $c) => Presenters::cyberIncident($c), 'detected_at');
    }

    public function cyberIncident(Request $request, string $case): JsonResponse
    {
        $this->api->authorize($request, 'security.incident.manage', CommandScope::global());
        $model = CyberIncident::query()->find($case) ?? CyberIncident::query()->where('number', strtoupper($case))->first();
        if ($model === null) {
            throw DomainError::notFound('cyber_incident');
        }

        return $this->ok(Presenters::cyberIncident($model));
    }

    public function openCyberIncident(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'], 'severity' => ['nullable', 'in:p1,p2,p3,p4'], 'detected_at' => ['nullable', 'date'], 'affected_services' => ['nullable', 'array'], 'affected_services.*' => ['string'],
            'jurisdictions' => ['nullable', 'array'], 'jurisdictions.*' => ['string', 'size:2'], 'personal_data_breach' => ['nullable', 'boolean'], 'life_safety_crime_suspicion' => ['nullable', 'boolean'], 'nis2_scope' => ['nullable', 'boolean'],
            'summary' => ['nullable', 'string', 'max:8000'], 'open_status_incident' => ['nullable', 'boolean'], 'components' => ['nullable', 'array'], 'components.*' => ['string', 'max:40'],
        ]);

        return $this->dispatch(new ComplianceCommand($this->idempotencyKey($request, 'cyber.open'), ['op' => 'cyber.open'] + $data), $this->api->context($request), 201);
    }

    public function transitionCyberIncident(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['state' => ['required', 'in:OPEN,CONTAINED,REPORTED,CLOSED'], 'summary' => ['nullable', 'string', 'max:8000']]);

        return $this->dispatch(new ComplianceCommand("cyber.transition:{$case}:{$data['state']}", ['op' => 'cyber.transition', 'case_id' => $case] + $data), $this->api->context($request));
    }

    public function evidence(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:190'], 'sha256' => ['required', 'string', 'size:64'], 'path' => ['nullable', 'string', 'max:500'], 'legal_hold' => ['nullable', 'boolean']]);

        return $this->dispatch(new ComplianceCommand("cyber.evidence:{$case}:{$data['sha256']}", ['op' => 'cyber.evidence', 'case_id' => $case] + $data), $this->api->context($request), 201);
    }

    public function timers(Request $request): JsonResponse
    {
        if (! $this->api->can($request, 'compliance.case.manage', CommandScope::global())) {
            $this->api->authorize($request, 'security.incident.manage', CommandScope::global());
        }
        $query = ComplianceTimer::query()->when($request->filled('state'), fn ($q) => $q->where('state', (string) $request->query('state')));

        return $this->api->paginate($request, $query, fn (ComplianceTimer $t) => Presenters::timer($t), 'deadline_at');
    }

    public function submitTimer(Request $request, string $timer): JsonResponse
    {
        $data = $request->validate(['authority_reference' => ['required', 'string', 'max:120'], 'evidence' => ['nullable', 'array']]);

        return $this->dispatch(new ComplianceCommand("timer.submit:{$timer}", ['op' => 'timer.submit', 'timer_id' => $timer] + $data), $this->api->context($request));
    }

    public function waiveTimer(Request $request, string $timer): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->dispatch(new ComplianceCommand("timer.waive:{$timer}", ['op' => 'timer.waive', 'timer_id' => $timer] + $data), $this->api->context($request, null, $data['reason']));
    }

    // ── abuse ───────────────────────────────────────────────────────────────

    public function abuseCases(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'abuse.case.manage', CommandScope::global());
        $query = AbuseCase::query();
        foreach (['state', 'category', 'organization_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }

        return $this->api->paginate($request, $query, fn (AbuseCase $a) => Presenters::abuseCase($a, true));
    }

    public function abuseCase(Request $request, string $case): JsonResponse
    {
        $this->api->authorize($request, 'abuse.case.manage', CommandScope::global());
        $model = AbuseCase::query()->find($case) ?? AbuseCase::query()->where('number', strtoupper($case))->first();
        if ($model === null) {
            throw DomainError::notFound('abuse_case');
        }

        return $this->ok(Presenters::abuseCase($model, true));
    }

    public function triageAbuse(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:action,no_action'], 'reason' => ['required', 'string', 'min:10', 'max:4000']]);

        return $this->dispatch(new ComplianceCommand("abuse.triage:{$case}", ['op' => 'abuse.triage', 'case_id' => $case] + $data), $this->api->context($request, null, $data['reason']));
    }

    public function notifyAbuse(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['statement' => ['required', 'string', 'min:20', 'max:8000']]);

        return $this->dispatch(new ComplianceCommand("abuse.notify:{$case}", ['op' => 'abuse.notify', 'case_id' => $case] + $data), $this->api->context($request));
    }

    public function actionAbuse(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:content_removed,service_suspended,warning,none'], 'reason' => ['required', 'string', 'min:10', 'max:4000']]);

        return $this->dispatch(new ComplianceCommand("abuse.action:{$case}:{$data['action']}", ['op' => 'abuse.action', 'case_id' => $case] + $data), $this->api->context($request, null, $data['reason']));
    }

    public function closeAbuse(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        return $this->dispatch(new ComplianceCommand("abuse.close:{$case}", ['op' => 'abuse.close', 'case_id' => $case] + $data), $this->api->context($request));
    }

    // ── data requests & legal hold ───────────────────────────────────────────

    public function dataRequests(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'compliance.case.manage', CommandScope::global());

        return $this->api->paginate($request, DataRequest::query()->when($request->filled('state'), fn ($q) => $q->where('state', (string) $request->query('state'))), fn (DataRequest $r) => Presenters::dataRequest($r));
    }

    public function processDataRequests(Request $request): JsonResponse
    {
        return $this->dispatch(new ComplianceCommand('data_request.process:'.now()->timestamp, ['op' => 'data_request.process']), $this->api->context($request));
    }

    public function legalHold(Request $request, string $organization): JsonResponse
    {
        $data = $request->validate(['hold' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->dispatch(new ComplianceCommand("legal_hold:{$organization}:".($data['hold'] ? 'on' : 'off'), ['op' => 'legal_hold', 'organization_id' => $organization] + $data), $this->api->context($request, null, $data['reason']));
    }
}
