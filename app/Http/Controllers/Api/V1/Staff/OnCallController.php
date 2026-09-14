<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Onhost\Domain\Incidents\Commands\OnCallCommand;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Incidents\OnCallRota;
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

    /** The rota (audit §5r-1): the running shift and the next `days` days. */
    public function shifts(Request $request, OnCallRota $rota): JsonResponse
    {
        $this->api->authorize($request, 'incident.manage', CommandScope::global());

        return response()->json(['data' => $rota->upcoming((int) $request->query('days', 14)), 'on_call' => $rota->assignee(), 'hand_over' => $rota->handOverLine()]);
    }

    /** The rota as iCalendar (audit §5t-4), for a personal calendar subscription through the console session. */
    public function shiftsIcal(Request $request, OnCallRota $rota): Response
    {
        $this->api->authorize($request, 'incident.manage', CommandScope::global());

        return response($rota->ical((int) $request->query('days', 60)), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="onhost-oncall.ics"', 'Cache-Control' => 'no-store']);
    }

    /** A personal subscription URL for the rota (audit §5u-4); shown once, replaces the previous one. */
    public function feedToken(Request $request, OnCallRota $rota): JsonResponse
    {
        $this->api->authorize($request, 'incident.manage', CommandScope::global());

        return response()->json(['data' => ['url' => $rota->issueFeedToken($this->api->user($request), $this->api->context($request))]], 201);
    }

    /** The subscribed calendar (no session; the token is the key). */
    public function feed(OnCallRota $rota, string $token): Response
    {
        $ics = $rota->feedFor($token);
        abort_if($ics === null, 404);

        return response($ics, 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Cache-Control' => 'private, max-age=300']);
    }

    public function importShifts(Request $request): JsonResponse
    {
        $data = $request->validate(['ical' => ['required', 'string', 'max:512000']]);

        return $this->dispatch(new OnCallCommand($this->idempotencyKey($request, 'oncall.shift.import:'.md5($data['ical'])), ['op' => 'shift.import', 'ical' => $data['ical']]), $this->api->context($request));
    }

    public function addShift(Request $request): JsonResponse
    {
        $data = $request->validate(['user' => ['required', 'string', 'max:190'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new OnCallCommand($this->idempotencyKey($request, 'oncall.shift.add:'.md5(json_encode($data))), ['op' => 'shift.add'] + $data), $this->api->context($request), 201);
    }

    public function removeShift(Request $request, string $shift): JsonResponse
    {
        return $this->dispatch(new OnCallCommand($this->idempotencyKey($request, "oncall.shift.remove:{$shift}"), ['op' => 'shift.remove', 'shift_id' => $shift]), $this->api->context($request));
    }

    /** The pager's own webhook (no session): PagerDuty v3 signature or the inbound token decide. */
    public function inbound(Request $request, OnCallService $oncall, string $provider): JsonResponse
    {
        return response()->json(['data' => $oncall->inbound($provider, $request)], 202);
    }
}
