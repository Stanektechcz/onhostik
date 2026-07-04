<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()?->customer;

        if ($customer === null) {
            return response()->json(['error' => 'No customer account.'], 403);
        }

        $services = Service::query()
            ->where('customer_id', $customer->id)
            ->with(['product', 'monitors'])
            ->get()
            ->map(fn (Service $s) => [
                'id'            => $s->id,
                'uuid'          => $s->uuid,
                'label'         => $s->label,
                'status'        => $s->status->value,
                'product'       => $s->product?->name,
                'next_due_date' => $s->next_due_date?->toDateString(),
                'created_at'    => $s->created_at?->toIso8601String(),
                'monitors'      => $s->monitors->map(fn (Monitor $m) => [
                    'id'             => $m->id,
                    'name'           => $m->name,
                    'status'         => $m->status->value,
                    'uptime_percent' => $m->uptime_percent,
                    'last_check_at'  => $m->last_check_at?->toIso8601String(),
                ])->values()->all(),
            ]);

        return response()->json(['data' => $services]);
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null || $service->customer_id !== $customer->id, 403);

        $service->loadMissing(['product', 'monitors']);

        return response()->json([
            'data' => [
                'id'                  => $service->id,
                'uuid'                => $service->uuid,
                'label'               => $service->label,
                'status'              => $service->status->value,
                'provisioning_driver' => $service->provisioning_driver?->value,
                'product'             => $service->product?->name,
                'next_due_date'       => $service->next_due_date?->toDateString(),
                'suspended_at'        => $service->suspended_at?->toIso8601String(),
                'suspension_reason'   => $service->suspension_reason,
                'created_at'          => $service->created_at?->toIso8601String(),
                'monitors'            => $service->monitors->map(fn (Monitor $m) => [
                    'id'             => $m->id,
                    'name'           => $m->name,
                    'type'           => $m->type,
                    'target'         => $m->target,
                    'status'         => $m->status->value,
                    'uptime_percent' => $m->uptime_percent,
                    'ssl_expires_at' => $m->ssl_expires_at?->toDateString(),
                    'last_check_at'  => $m->last_check_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }
}
