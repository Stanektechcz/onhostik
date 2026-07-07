<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ReportSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $schedules = ReportSchedule::where('is_active', true)
            ->orderBy('name')
            ->paginate(10);

        return view('panel.report-schedules.index', compact('schedules'));
    }
}
