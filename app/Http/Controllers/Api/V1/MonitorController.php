<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Monitoring\Models\Monitor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        // Monitors for the customer's services only
        $serviceIds = $customer->services()->pluck('id');

        $monitors = Monitor::query()
            ->whereIn('service_id', $serviceIds)
            ->with('service:id,label,uuid')
            ->orderByDesc('last_check_at')
            ->get()
            ->map(fn (Monitor $m) => [
                'id'              => $m->id,
                'name'            => $m->name,
                'type'            => $m->type,
                'target'          => $m->target,
                'status'          => $m->status->value,
                'uptime_percent'  => $m->uptime_percent,
                'last_check_at'   => $m->last_check_at?->toIso8601String(),
                'ssl_expires_at'  => $m->ssl_expires_at?->toDateString(),
                'service_id'      => $m->service_id,
                'service_label'   => $m->service?->label,
            ]);

        return response()->json(['data' => $monitors]);
    }
}
