<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportScheduleRunController extends Controller
{
    public function store(Request $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        $reportSchedule->update([
            'last_run_at' => now(),
            'next_run_at' => match ($reportSchedule->frequency) {
                'weekly'  => now()->addWeek(),
                'monthly' => now()->addMonth(),
                default   => now()->addDay(),
            },
        ]);

        return back()->with('status', "Report '{$reportSchedule->name}' byl spuštěn.");
    }
}
