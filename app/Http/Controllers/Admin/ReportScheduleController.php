<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportScheduleController extends Controller
{
    public function index(): View
    {
        $schedules = ReportSchedule::orderByDesc('created_at')->paginate(15);

        return view('admin.report-schedules.index', compact('schedules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $recipientsRaw = $request->input('recipients_raw') ?? '';
        $request->merge([
            'recipients' => array_values(array_filter(array_map('trim', explode(',', $recipientsRaw)))),
        ]);

        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'report_type'  => 'required|string|max:60',
            'frequency'    => 'required|in:daily,weekly,monthly',
            'recipients'   => 'required|array',
            'recipients.*' => 'email',
            'format'       => 'required|in:pdf,csv,json',
            'is_active'    => 'boolean',
        ]);

        ReportSchedule::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Plán reportu vytvořen.');
    }

    public function destroy(ReportSchedule $reportSchedule): RedirectResponse
    {
        $reportSchedule->delete();

        return back()->with('status', 'Plán reportu smazán.');
    }
}
