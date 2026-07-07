<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceBackupLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ServiceBackupLogController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $serviceIds = Service::where('customer_id', $customerId)->pluck('id');

        $logs = ServiceBackupLog::whereIn('service_id', $serviceIds)
            ->orderByDesc('started_at')
            ->paginate(15);

        return view('panel.service-backup-logs.index', compact('logs'));
    }
}
