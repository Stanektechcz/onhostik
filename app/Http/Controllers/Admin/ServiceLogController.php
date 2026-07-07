<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ServiceLog::with(['service'])
            ->when($request->service_id, fn($q) => $q->where('service_id', $request->service_id))
            ->when($request->level, fn($q) => $q->where('level', $request->level))
            ->orderByDesc('logged_at')
            ->paginate(50);

        $levels = ['debug', 'info', 'warning', 'error'];

        return view('admin.service-logs.index', compact('logs', 'levels'));
    }
}
