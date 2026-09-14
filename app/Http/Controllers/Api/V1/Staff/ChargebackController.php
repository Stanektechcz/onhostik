<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Billing\ChargebackAnalyst;
use Onhost\Domain\Billing\ChargebackService;
use Onhost\Domain\Billing\Commands\ChargebackStaffCommand;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;

/** Chargeback requests for technical support: the queue, the decision, the returned share. */
final class ChargebackController extends ApiController
{
    public function index(Request $request, ChargebackService $chargebacks): JsonResponse
    {
        $this->api->authorize($request, 'staff.service.manage', CommandScope::global());
        $state = (string) $request->query('state', '');
        $query = ChargebackRequest::query()->orderByDesc('created_at');
        if ($state !== '') {
            $query->whereIn('state', $state === 'open' ? ChargebackRequest::OPEN : [$state]);
        }
        $rows = $query->limit(200)->get();
        $services = Service::query()->withTrashed()->whereIn('id', $rows->pluck('service_id'))->get()->keyBy('id');
        $organizations = Organization::query()->whereIn('id', $rows->pluck('organization_id'))->get()->keyBy('id');

        return response()->json(['data' => ['rows' => $rows->map(fn (ChargebackRequest $r) => $chargebacks->present($r) + [
            'service' => ($s = $services->get($r->service_id)) ? ['label' => $s->label ?: $s->name, 'product_key' => $s->product_key, 'state' => $s->state] : null,
            'organization' => $organizations->get($r->organization_id)?->name,
        ])->all(), 'percent' => $chargebacks->percent(), 'open' => ChargebackRequest::query()->whereIn('state', ChargebackRequest::OPEN)->count()]]);
    }

    public function decide(Request $request, string $chargeback): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['nullable', 'string', 'max:2000']]);

        return $this->dispatch(new ChargebackStaffCommand($this->idempotencyKey($request, "chargeback.decide:{$chargeback}"), ['op' => 'decide', 'chargeback_id' => $chargeback] + $data), $this->api->context($request, null, $data['reason'] ?? null));
    }

    public function settings(Request $request, ChargebackService $chargebacks): JsonResponse
    {
        $this->api->authorize($request, 'staff.service.manage', CommandScope::global());

        return response()->json(['data' => ['percent' => $chargebacks->percent(), 'default' => (int) config('onhost.chargeback.percent', 70)]]);
    }

    /** Why customers leave (audit §5j-6): requests clustered per product, node and theme; clusters above the threshold. */
    public function analytics(Request $request, ChargebackAnalyst $analyst): JsonResponse
    {
        $this->api->authorize($request, 'staff.service.manage', CommandScope::global());

        return response()->json(['data' => $analyst->analytics(max(1, min(365, (int) $request->query('days', 90))))]);
    }

    /** Opens the internal incidents for the clusters above the threshold now (the scheduler does it daily). */
    public function analyse(Request $request): JsonResponse
    {
        return $this->dispatch(new ChargebackStaffCommand($this->idempotencyKey($request, 'chargeback.analyse:'.now()->format('YmdHi')), ['op' => 'analyse']), $this->api->context($request));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['percent' => ['required', 'integer', 'min:0', 'max:100'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new ChargebackStaffCommand($this->idempotencyKey($request, 'chargeback.settings:'.now()->format('YmdHi')), ['op' => 'settings', 'percent' => (int) $data['percent'], 'reason' => $data['reason'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null));
    }
}
