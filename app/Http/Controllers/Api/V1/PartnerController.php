<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Commands\PartnerPortalCommand;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerPresenters;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Partner portal (Onhost-partner.dc.html): overview, clients, commissions, payouts, white-label, assets. */
final class PartnerController extends ApiController
{
    public function overview(Request $request, PartnerService $partners): JsonResponse
    {
        [$partner] = $this->partner($request);

        return $this->ok($partners->overview($partner));
    }

    public function clients(Request $request, PartnerService $partners): JsonResponse
    {
        [$partner] = $this->partner($request);

        return $this->ok($partners->clients($partner)->values()->all());
    }

    public function commissions(Request $request, PartnerService $partners): JsonResponse
    {
        [$partner] = $this->partner($request);

        return $this->ok(['balance' => $partners->balance($partner), 'months' => $partners->commissionMonths($partner), 'rules' => ['model' => $partner->model, 'rate' => $partner->rate_pct, 'min_payout' => config('onhost.partners.min_payout_minor'), 'tiers' => config('onhost.partners.tiers')]]);
    }

    public function payouts(Request $request, PartnerService $partners): JsonResponse
    {
        [$partner] = $this->partner($request);
        $list = PartnerPayout::query()->where('partner_id', $partner->id)->orderByDesc('requested_at')->get()->map(fn (PartnerPayout $p) => PartnerPresenters::payout($p))->values()->all();

        return $this->ok(['balance' => $partners->balance($partner), 'iban_masked' => PartnerPresenters::partner($partner)['iban_masked'], 'payouts' => $list]);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        [, $organization] = $this->partner($request);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1'], 'iban' => ['required', 'string', 'max:34'], 'method' => ['nullable', 'in:bank_transfer,offset']]);

        return $this->dispatch(new PartnerPortalCommand($organization->id, $this->idempotencyKey($request, "partner.payout:{$organization->id}"), ['op' => 'payout.request'] + $data), $this->api->context($request, $organization), 201);
    }

    /** The commission model is a contract term (audit §5m-1): the partner asks, finance decides, the change takes effect next month. */
    public function requestModel(Request $request): JsonResponse
    {
        [, $organization] = $this->partner($request);
        $data = $request->validate(['model' => ['required', 'in:share,oneoff'], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->dispatch(new PartnerPortalCommand($organization->id, $this->idempotencyKey($request, "partner.model:{$organization->id}:".now()->format('YmdHi')), ['op' => 'model.request', 'model' => $data['model'], 'note' => $data['note'] ?? null]), $this->api->context($request, $organization, $data['note'] ?? null), 201);
    }

    /** Every contract term with its state (audit §5n-1): the portal's contract block. */
    public function changes(Request $request, PartnerService $partners): JsonResponse
    {
        [$partner] = $this->partner($request);

        return $this->ok($partners->terms($partner));
    }

    public function requestChange(Request $request): JsonResponse
    {
        [, $organization] = $this->partner($request);
        $data = $request->validate(['kind' => ['required', 'in:'.implode(',', PartnerService::CHANGE_KINDS)], 'value' => ['required', 'string', 'max:20'], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->dispatch(new PartnerPortalCommand($organization->id, $this->idempotencyKey($request, "partner.change:{$organization->id}:{$data['kind']}:".now()->format('YmdHi')), ['op' => 'change.request', 'kind' => $data['kind'], 'value' => $data['value'], 'note' => $data['note'] ?? null]), $this->api->context($request, $organization, $data['note'] ?? null), 201);
    }

    public function modelRequest(Request $request, PartnerService $partners): JsonResponse
    {
        [$partner] = $this->partner($request);

        return $this->ok(['request' => $partners->modelRequest($partner), 'model' => $partner->model, 'pending_model' => $partner->pending_model, 'model_effective_from' => $partner->model_effective_from?->toDateString()]);
    }

    public function whitelabel(Request $request): JsonResponse
    {
        [$partner] = $this->partner($request);

        return $this->ok(($partner->whitelabel ?? []) + ['cname_target' => config('onhost.partners.whitelabel_cname')]);
    }

    public function updateWhitelabel(Request $request): JsonResponse
    {
        [, $organization] = $this->partner($request);
        $data = $request->validate(['domain' => ['nullable', 'string', 'max:253'], 'hide_brand' => ['nullable', 'boolean'], 'own_mail' => ['nullable', 'boolean'], 'own_prices' => ['nullable', 'boolean'], 'own_support' => ['nullable', 'boolean']]);

        return $this->dispatch(new PartnerPortalCommand($organization->id, $this->idempotencyKey($request, "partner.whitelabel:{$organization->id}"), ['op' => 'whitelabel'] + $data), $this->api->context($request, $organization));
    }

    public function assets(Request $request): JsonResponse
    {
        $this->partner($request);

        return $this->ok(config('onhost.partners.assets', []));
    }

    /** Apply from inside the panel (signed-in organization). */
    public function apply(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['company' => ['nullable', 'string', 'max:190'], 'clients' => ['nullable', 'string', 'max:40'], 'site' => ['nullable', 'string', 'max:190'], 'note' => ['nullable', 'string', 'max:4000'], 'model' => ['nullable', 'in:share,oneoff']]);

        return $this->dispatch(new PartnerPortalCommand($organization->id, "partner.apply:{$organization->id}", ['op' => 'apply'] + $data), $this->api->context($request, $organization), 201);
    }

    /** @return array{0: Partner, 1: Organization} */
    private function partner(Request $request): array
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'organization.read', CommandScope::organization($organization->id));
        $partner = Partner::query()->where('organization_id', $organization->id)->first();
        if ($partner === null) {
            throw new DomainError('partner_missing', 'This organization is not enrolled in the partner programme.', 404);
        }
        if ($partner->state !== 'active') {
            throw new DomainError('partner_not_active', "Partner account is {$partner->state}.", 403, ['state' => $partner->state]);
        }

        return [$partner, $organization];
    }
}
