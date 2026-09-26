<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Partners\Commands\PartnerCommand;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerChangeRequest;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerPresenters;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Admin partner administration: applications, tiers, payouts. */
final class PartnerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'partner.manage', CommandScope::global());
        $query = Partner::query()->when($request->filled('state'), fn ($q) => $q->where('state', (string) $request->query('state')));

        return $this->api->paginate($request, $query, fn (Partner $p) => PartnerPresenters::partner($p, true));
    }

    /** Contract change requests (audit §5m-1). */
    public function requests(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'partner.manage', CommandScope::global());
        $state = (string) $request->query('state', 'requested');
        $query = PartnerChangeRequest::query()->orderByDesc('created_at')->orderByDesc('id');
        if ($state !== 'all') {
            $query->where('state', $state);
        }
        $rows = $query->limit(200)->get();
        $partners = Partner::query()->whereIn('id', $rows->pluck('partner_id'))->get()->keyBy('id');

        return response()->json(['data' => $rows->map(fn ($r) => PartnerService::presentRequest($r) + ['partner_code' => $partners->get($r->partner_id)?->code, 'partner_organization_id' => $partners->get($r->partner_id)?->organization_id])->values()->all()]);
    }

    public function decideRequest(Request $request, string $partnerRequest): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->dispatch(new PartnerCommand($this->idempotencyKey($request, "partner.model.decide:{$partnerRequest}"), ['op' => 'model.decide', 'request_id' => $partnerRequest, 'decision' => $data['decision'], 'note' => $data['note'] ?? null]), $this->api->context($request, null, $data['note'] ?? null));
    }

    public function show(Request $request, string $partner, PartnerService $partners): JsonResponse
    {
        $this->api->authorize($request, 'partner.manage', CommandScope::global());
        $model = Partner::query()->find($partner) ?? Partner::query()->where('code', strtoupper($partner))->first();
        if ($model === null) {
            throw DomainError::notFound('partner');
        }

        return $this->ok(PartnerPresenters::partner($model, true) + ['balance' => $partners->balance($model), 'clients' => $partners->clients($model)->values()->all(), 'months' => $partners->commissionMonths($model)]);
    }

    public function approve(Request $request, string $partner): JsonResponse
    {
        return $this->dispatch(new PartnerCommand("partner.approve:{$partner}", ['op' => 'approve', 'partner_id' => $partner]), $this->api->context($request));
    }

    public function state(Request $request, string $partner): JsonResponse
    {
        $data = $request->validate(['state' => ['required', 'in:active,suspended,closed'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->dispatch(new PartnerCommand("partner.state:{$partner}:{$data['state']}", ['op' => 'state', 'partner_id' => $partner] + $data), $this->api->context($request, null, $data['reason']));
    }

    public function payouts(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'partner.manage', CommandScope::global());
        $query = PartnerPayout::query()->when($request->filled('state'), fn ($q) => $q->where('state', (string) $request->query('state')));

        return $this->api->paginate($request, $query, fn (PartnerPayout $p) => PartnerPresenters::payout($p, true), 'requested_at');
    }

    public function approvePayout(Request $request, string $payout): JsonResponse
    {
        return $this->dispatch(new PartnerCommand("payout.approve:{$payout}", ['op' => 'payout.approve', 'payout_id' => $payout]), $this->api->context($request));
    }

    public function rejectPayout(Request $request, string $payout): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->dispatch(new PartnerCommand("payout.reject:{$payout}", ['op' => 'payout.reject', 'payout_id' => $payout] + $data), $this->api->context($request, null, $data['reason']));
    }

    public function payPayout(Request $request, string $payout): JsonResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:120']]);

        return $this->dispatch(new PartnerCommand("payout.pay:{$payout}", ['op' => 'payout.pay', 'payout_id' => $payout] + $data), $this->api->context($request));
    }

    public function recomputeTiers(Request $request): JsonResponse
    {
        return $this->dispatch(new PartnerCommand('partner.tiers:'.now()->timestamp, ['op' => 'tiers.recompute']), $this->api->context($request));
    }
}
