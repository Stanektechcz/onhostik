<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceMigrationBatch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceMigrationStatusController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $serviceIds = Service::where('customer_id', $customerId)
            ->pluck('id')
            ->toArray();

        $activeMigrations = ServiceMigrationBatch::whereIn('status', ['pending', 'running'])
            ->get()
            ->filter(function (ServiceMigrationBatch $batch) use ($serviceIds): bool {
                $batchServiceIds = $batch->service_ids ?? [];
                return count(array_intersect($batchServiceIds, $serviceIds)) > 0;
            });

        $completedMigrations = ServiceMigrationBatch::where('status', 'completed')
            ->get()
            ->filter(function (ServiceMigrationBatch $batch) use ($serviceIds): bool {
                $batchServiceIds = $batch->service_ids ?? [];
                return count(array_intersect($batchServiceIds, $serviceIds)) > 0;
            });

        return view('panel.service-migration-status.index', compact(
            'activeMigrations',
            'completedMigrations',
        ));
    }
}
