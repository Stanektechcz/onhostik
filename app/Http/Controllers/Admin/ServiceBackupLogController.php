<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceBackupLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ServiceBackupLogController extends Controller
{
    public function index(Request $request): View
    {
        $serviceId = $request->query('service_id');
        $status    = $request->query('status');

        $logs = ServiceBackupLog::when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('started_at')
            ->paginate(20);

        return view('admin.service-backup-logs.index', compact('logs', 'serviceId', 'status'));
    }
}
