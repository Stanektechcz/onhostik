<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Incidents\Commands\OnCallCommand;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Platform\Commands\CommandScope;

/** On-call alerts (audit §5q-1): the list, acknowledge / resolve from the console, a test page, the pager's call-back. */
final class OnCallController extends ApiController
{
    public function index(Request $request, OnCallService $oncall): JsonResponse
    {
        $this->api->authorize($request, 'incident.manage', CommandScope::global());
        $state = (string) $request->query('state', 'active');
        $query = OnCallAlert::query()->orderByDesc('created_at');
        if ($state === 'active') {
            $query->whereIn('state', [OnCallAlert::OPEN, OnCallAlert::ACKED, OnCallAlert::ESCALATED]);
        } elseif ($state !== 'all') {
            $query->where('state', $state);
        }

        return response()->json(['data' => $query->limit(200)->get()->map(fn (OnCallAlert $a) => OnCallService::present($a))->values()->all(), 'status' => $oncall->status()]);
    }

    public function acknowledge(Request $request, string $alert): JsonResponse
    {
        return $this->dispatch(new OnCallCommand($this->idempotencyKey($request, "oncall.ack:{$alert}"), ['op' => 'ack', 'alert_id' => $alert]), $this->api->context($request));
    }

    public function resolve(Request $request, string $alert): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new OnCallCommand($this->idempotencyKey($request, "oncall.resolve:{$alert}"), ['op' => 'resolve', 'alert_id' => $alert]), $this->api->context($request, null, $data['note'] ?? null));
    }

    public function test(Request $request): JsonResponse
    {
        return $this->dispatch(new OnCallCommand($this->idempotencyKey($request, 'oncall.test:'.now()->format('YmdHi')), ['op' => 'test']), $this->api->context($request), 201);
    }

    /** The pager's own webhook (no session): PagerDuty v3 signature or the inbound token decide. */
    public function inbound(Request $request, OnCallService $oncall, string $provider): JsonResponse
    {
        return response()->json(['data' => $oncall->inbound($provider, $request)], 202);
    }
}
