<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Incidents\Presenters;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Public abuse reporting (DSA Art. 16) and the customer's GDPR / Data Act requests and abuse complaints. */
final class ComplianceController extends ApiController
{
    /** Public notice form: no session; rate-limited. */
    public function reportAbuse(Request $request, ComplianceService $compliance): JsonResponse
    {
        $data = $request->validate([
            'reporter.name' => ['required', 'string', 'max:120'], 'reporter.email' => ['required', 'email', 'max:190'], 'reporter.organization' => ['nullable', 'string', 'max:190'], 'reporter.trusted_flagger' => ['nullable', 'boolean'],
            'category' => ['required', 'in:'.implode(',', AbuseCase::CATEGORIES)], 'allegation' => ['required', 'string', 'min:20', 'max:8000'], 'target_url' => ['nullable', 'url', 'max:500'],
            'jurisdiction' => ['nullable', 'string', 'size:2'], 'evidence' => ['nullable', 'array', 'max:20'], 'evidence.*.kind' => ['required', 'string', 'max:40'], 'evidence.*.sha256' => ['nullable', 'string', 'size:64'], 'evidence.*.url' => ['nullable', 'url', 'max:500'],
            'good_faith' => ['accepted'], // DSA Art. 16(2)(d): bona fide statement
        ]);
        $case = $compliance->reportAbuse($data, $this->api->context($request));

        return response()->json(['data' => ['number' => $case->number, 'state' => $case->state, 'received_at' => $case->created_at->toIso8601String(), 'message' => 'Oznámení jsme přijali. O rozhodnutí vás budeme informovat e-mailem.']], 201);
    }

    /** Customer: abuse cases against my organization (statement of reasons, appeal window). */
    public function abuseCases(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->api->paginate($request, AbuseCase::query()->where('organization_id', $organization->id)->whereNotIn('state', ['RECEIVED']), fn (AbuseCase $a) => Presenters::abuseCase($a));
    }

    public function appeal(Request $request, string $case, ComplianceService $compliance): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['text' => ['required', 'string', 'min:20', 'max:8000']]);
        $model = AbuseCase::query()->where('organization_id', $organization->id)->where(fn ($q) => $q->where('id', $case)->orWhere('number', strtoupper($case)))->first();
        if ($model === null) {
            throw DomainError::notFound('abuse_case');
        }

        return $this->ok(Presenters::abuseCase($compliance->appealAbuse($model, $data['text'], $this->api->context($request, $organization))));
    }

    // ── data requests ────────────────────────────────────────────────────────

    public function dataRequests(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->api->paginate($request, DataRequest::query()->where('organization_id', $organization->id), fn (DataRequest $r) => Presenters::dataRequest($r));
    }

    public function requestData(Request $request, ComplianceService $compliance): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $data = $request->validate(['kind' => ['required', 'in:export,deletion,switching'], 'reason' => ['nullable', 'string', 'max:250']]);
        $model = $compliance->requestData($organization, $data['kind'], $this->api->context($request, $organization, $data['reason'] ?? null), $data['reason'] ?? null);

        return response()->json(['data' => Presenters::dataRequest($model)], 202);
    }

    /** A signed link for the export (audit §5j-7): seven days at most, one active link per export. */
    public function link(Request $request, ComplianceService $compliance, string $dataRequest): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $model = DataRequest::query()->where('organization_id', $organization->id)->find($dataRequest) ?? throw DomainError::notFound('data_request');

        return response()->json(['data' => $compliance->downloadLink($model, $this->api->context($request, $organization))], 201);
    }

    public function download(Request $request, string $dataRequest): StreamedResponse
    {
        $organization = $this->api->organization($request);
        $model = DataRequest::query()->where('organization_id', $organization->id)->find($dataRequest);
        if ($model === null) {
            throw DomainError::notFound('data_request');
        }
        if ($model->state !== 'ready' || $model->file_path === null || ($model->expires_at !== null && $model->expires_at < now())) {
            throw new DomainError('data_export_not_ready', 'The export is not ready or has expired.', 409, ['state' => $model->state]);
        }

        return Storage::disk('local')->download($model->file_path, "onhost-export-{$model->id}.json", ['Content-Type' => 'application/json']);
    }
}
