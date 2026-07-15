<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceLiveStatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Read-only live snapshot of a service from its backend panel for the
 * Service 360° detail page. Never triggers a write — see
 * ServiceLiveStatusService for the gate semantics.
 */
class ServiceLiveStatusController extends Controller
{
    public function __invoke(Service $service, ServiceLiveStatusService $liveStatus): JsonResponse
    {
        return response()->json($liveStatus->fetch($service));
    }
}
