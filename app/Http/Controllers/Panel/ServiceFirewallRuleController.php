<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceFirewallRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceFirewallRuleController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $serviceId = $request->query('service_id');

        if ($serviceId) {
            $service = Service::findOrFail($serviceId);
            abort_unless($service->customer_id === $customerId, 403);
        }

        $rules = ServiceFirewallRule::when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
            ->whereIn('service_id', Service::where('customer_id', $customerId)->select('id'))
            ->orderByDesc('created_at')
            ->paginate(15);

        $services = Service::where('customer_id', $customerId)->get();

        return view('panel.service-firewall-rules.index', compact('rules', 'services', 'serviceId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $validated = $request->validate([
            'service_id'  => 'required|integer',
            'direction'   => 'required|in:in,out,both',
            'protocol'    => 'required|in:tcp,udp,icmp,any',
            'port_from'   => 'nullable|integer|min:1|max:65535',
            'port_to'     => 'nullable|integer|min:1|max:65535',
            'ip_cidr'     => 'required|string|max:50',
            'action'      => 'required|in:allow,deny',
            'description' => 'nullable|string|max:500',
        ]);

        $service = Service::findOrFail($validated['service_id']);
        abort_unless($service->customer_id === $customerId, 403);

        $validated['is_active']  = true;
        $validated['created_by'] = $request->user()->id;

        ServiceFirewallRule::create($validated);

        return back()->with('status', 'Pravidlo firewallu přidáno.');
    }

    public function destroy(Request $request, ServiceFirewallRule $serviceFirewallRule): RedirectResponse
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $service = Service::findOrFail($serviceFirewallRule->service_id);
        abort_unless($service->customer_id === $customerId, 403);

        $serviceFirewallRule->delete();

        return back()->with('status', 'Pravidlo odstraněno.');
    }
}
