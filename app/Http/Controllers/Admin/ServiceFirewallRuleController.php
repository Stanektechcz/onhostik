<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceFirewallRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceFirewallRuleController extends Controller
{
    public function index(Request $request): View
    {
        $serviceId = $request->query('service_id');
        $rules = ServiceFirewallRule::when($serviceId, fn($q) => $q->where('service_id', $serviceId))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.service-firewall-rules.index', compact('rules', 'serviceId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id'  => 'required|integer',
            'direction'   => 'required|in:in,out,both',
            'protocol'    => 'required|in:tcp,udp,icmp,any',
            'port_from'   => 'nullable|integer|min:1|max:65535',
            'port_to'     => 'nullable|integer|min:1|max:65535|gte:port_from',
            'ip_cidr'     => 'required|string|max:50',
            'action'      => 'required|in:allow,deny',
            'is_active'   => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['created_by'] = $request->user()->id;

        ServiceFirewallRule::create($validated);

        return back()->with('status', 'Pravidlo firewallu vytvořeno.');
    }

    public function update(Request $request, ServiceFirewallRule $serviceFirewallRule): RedirectResponse
    {
        $serviceFirewallRule->update(['is_active' => !$serviceFirewallRule->is_active]);

        return back()->with('status', 'Stav pravidla aktualizován.');
    }

    public function destroy(ServiceFirewallRule $serviceFirewallRule): RedirectResponse
    {
        $serviceFirewallRule->delete();

        return back()->with('status', 'Pravidlo odstraněno.');
    }
}
