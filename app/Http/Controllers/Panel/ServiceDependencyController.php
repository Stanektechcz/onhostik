<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Products\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceDependencyController extends Controller
{
    public function check(Request $request, Service $service): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_if($service->customer_id !== $customer?->id, 403);

        $validated = $request->validate([
            'target_plan_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        /** @var Product $targetPlan */
        $targetPlan = Product::findOrFail($validated['target_plan_id']);

        $warnings = [];

        $rawResources = $targetPlan->resources;
        $targetResources = is_array($rawResources) ? $rawResources : [];

        $diskLimit = array_key_exists('disk_gb', $targetResources) ? (float) $targetResources['disk_gb'] : null;
        $diskUsage = $service->disk_usage_gb ?? null;
        if ($diskLimit !== null && $diskUsage !== null && (float) $diskUsage > $diskLimit) {
            $warnings[] = 'Aktuální využití disku (' . $diskUsage . ' GB) přesahuje limit nového plánu (' . $diskLimit . ' GB).';
        }

        $bwLimit = array_key_exists('bandwidth_gb', $targetResources) ? (float) $targetResources['bandwidth_gb'] : null;
        $bwUsage = $service->bandwidth_usage_gb ?? null;
        if ($bwLimit !== null && $bwUsage !== null && (float) $bwUsage > $bwLimit) {
            $warnings[] = 'Aktuální využití přenosů přesahuje limit nového plánu.';
        }

        return response()->json([
            'safe'     => count($warnings) === 0,
            'warnings' => $warnings,
        ]);
    }
}
