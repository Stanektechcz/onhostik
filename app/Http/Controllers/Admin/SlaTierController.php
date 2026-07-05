<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Monitoring\Models\SlaTier;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlaTierController extends Controller
{
    public function index(): View
    {
        $tiers = SlaTier::orderBy('uptime_percent_x100', 'desc')->get();
        return view('admin.sla-tiers.index', compact('tiers'));
    }

    public function create(): View
    {
        return view('admin.sla-tiers.form', ['tier' => new SlaTier()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                   => 'required|string|max:100',
            'slug'                   => 'required|string|max:50|unique:sla_tiers,slug',
            'uptime_percent_x100'    => 'required|integer|min:9000|max:9999',
            'response_time_minutes'  => 'required|integer|min:1',
            'resolution_time_hours'  => 'required|integer|min:1',
            'credit_percent_per_hour'=> 'required|integer|min:1|max:100',
            'max_credit_percent'     => 'required|integer|min:1|max:100',
            'is_active'              => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        SlaTier::create($data);

        return redirect()->route('admin.sla-tiers.index')->with('status', 'SLA tier vytvořen.');
    }

    public function edit(SlaTier $slaTier): View
    {
        return view('admin.sla-tiers.form', ['tier' => $slaTier]);
    }

    public function update(Request $request, SlaTier $slaTier): RedirectResponse
    {
        $data = $request->validate([
            'name'                   => 'required|string|max:100',
            'slug'                   => 'required|string|max:50|unique:sla_tiers,slug,' . $slaTier->id,
            'uptime_percent_x100'    => 'required|integer|min:9000|max:9999',
            'response_time_minutes'  => 'required|integer|min:1',
            'resolution_time_hours'  => 'required|integer|min:1',
            'credit_percent_per_hour'=> 'required|integer|min:1|max:100',
            'max_credit_percent'     => 'required|integer|min:1|max:100',
            'is_active'              => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $slaTier->update($data);

        return redirect()->route('admin.sla-tiers.index')->with('status', 'SLA tier aktualizován.');
    }

    public function destroy(SlaTier $slaTier): RedirectResponse
    {
        $slaTier->delete();
        return redirect()->route('admin.sla-tiers.index')->with('status', 'SLA tier smazán.');
    }
}
