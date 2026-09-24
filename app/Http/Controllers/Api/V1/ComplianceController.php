<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Onhost\Domain\Compliance\Commands\DataRequestCommand;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Incidents\Presenters;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Files\FileStore;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * Through the bus, like every other write: an erasure of the whole account takes the owner's `organization.close`
     * and a fresh step-up (DataRequestCommand), and it used to take the permission to edit the organization's profile.
     */
    public function requestData(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['kind' => ['required', 'in:export,deletion,switching'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new DataRequestCommand($organization->id, $this->requestKey($request, 'data-request:'.$data['kind']), ['op' => 'request'] + $data),
            $this->api->context($request, $organization, $data['reason'] ?? null), 202);
    }

    /**
     * A request is a fresh intention each time unless the client names it: the key derived from the body would replay
     * the first answer — a second export would not hear "one is already running", and a new erasure asked for after
     * the first one was cancelled would get the cancelled one back. A client that sends `Idempotency-Key` still gets
     * the same answer for the same key.
     */
    private function requestKey(Request $request, string $prefix): string
    {
        $header = $request->headers->get('Idempotency-Key');

        return is_string($header) && $header !== '' ? $this->idempotencyKey($request, $prefix) : $prefix.':'.Str::uuid()->toString();
    }

    /** Stopping a scheduled erasure while its grace period runs. */
    public function cancel(Request $request, string $dataRequest): JsonResponse
    {
        $organization = $this->api->organization($request);

        return $this->dispatch(new DataRequestCommand($organization->id, $this->idempotencyKey($request, 'data-request.cancel:'.$dataRequest), ['op' => 'cancel', 'data_request_id' => $dataRequest]),
            $this->api->context($request, $organization));
    }

    /** A signed link for the export (audit §5j-7): seven days at most, one active link per export. */
    public function link(Request $request, ComplianceService $compliance, string $dataRequest): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $model = DataRequest::query()->where('organization_id', $organization->id)->find($dataRequest) ?? throw DomainError::notFound('data_request');

        return response()->json(['data' => $compliance->downloadLink($model, $this->api->context($request, $organization))], 201);
    }

    public function download(Request $request, FileStore $files, AuditRecorder $audit, string $dataRequest): Response
    {
        $organization = $this->api->organization($request);
        // The archive holds every member's name and e-mail, the billing identity, invoices, tickets and thousands of audit rows
        // with addresses. Asking for it and linking it need `organization.manage`; downloading it needed nothing — any member,
        // and any staff account that may merely READ customers, could pull it, and nothing was written down.
        $this->api->authorize($request, 'organization.manage', CommandScope::organization($organization->id));
        $model = DataRequest::query()->where('organization_id', $organization->id)->find($dataRequest);
        if ($model === null) {
            throw DomainError::notFound('data_request');
        }
        if ($model->state !== 'ready' || $model->file_path === null || ($model->expires_at !== null && $model->expires_at < now())) {
            throw new DomainError('data_export_not_ready', 'The export is not ready or has expired.', 409, ['state' => $model->state]);
        }

        $audit->record($this->api->context($request, $organization), 'compliance.data_export.download', 'succeeded', ['data_request' => $model->id], 'data_request', $model->id);

        return $files->download($model->file_path, "onhost-export-{$model->id}.json", 'application/json', ['X-Robots-Tag' => 'noindex']); // the store the export was written to (local or S3)
    }
}
