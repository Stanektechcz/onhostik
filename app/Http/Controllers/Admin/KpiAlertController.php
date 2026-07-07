<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KpiAlert;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KpiAlertController extends Controller
{
    private const METRICS = [
        'overdue_invoices_count'   => 'Počet po splatnosti',
        'failed_backups_24h'       => 'Neúspěšné zálohy (24 h)',
        'suspended_services_count' => 'Pozastavené služby',
        'open_tickets_count'       => 'Otevřené tikety',
        'monthly_revenue_czk'      => 'Měsíční příjem (CZK)',
    ];

    public function index(): View
    {
        return view('admin.kpi-alerts', [
            'alerts'          => KpiAlert::query()->orderBy('metric')->get(),
            'metrics'         => self::METRICS,
            'triggeredCount'  => KpiAlert::query()->whereNotNull('triggered_at')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'metric'    => ['required', 'string', 'in:' . implode(',', array_keys(self::METRICS))],
            'operator'  => ['required', 'in:gte,lte'],
            'threshold' => ['required', 'numeric', 'min:0'],
        ]);

        KpiAlert::create([
            'metric'    => $validated['metric'],
            'operator'  => $validated['operator'],
            'threshold' => (float) $validated['threshold'],
            'is_active' => true,
        ]);

        return back()->with('status', 'KPI alert byl přidán.');
    }

    public function update(Request $request, KpiAlert $kpiAlert): RedirectResponse
    {
        $validated = $request->validate([
            'threshold' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kpiAlert->update([
            'threshold' => (float) $validated['threshold'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('status', 'KPI alert byl upraven.');
    }

    public function destroy(KpiAlert $kpiAlert): RedirectResponse
    {
        $kpiAlert->delete();

        return back()->with('status', 'KPI alert byl smazán.');
    }
}
