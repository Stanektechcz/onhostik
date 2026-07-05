<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Security\Enums\WafRuleType;
use App\Domains\Security\Models\WafRule;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WafController extends Controller
{
    public function index(Service $service): View
    {
        $this->authorize('view', $service);

        $rules = WafRule::query()
            ->where('service_id', $service->id)
            ->latest('id')
            ->get();

        $events = \App\Domains\Security\Models\WafEvent::query()
            ->where('service_id', $service->id)
            ->orderByDesc('blocked_at')
            ->limit(20)
            ->get();

        return view('panel.waf.index', compact('service', 'rules', 'events'));
    }

    public function store(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        $types = array_column(WafRuleType::cases(), 'value');

        $validated = $request->validate([
            'type'  => ['required', 'string', 'in:' . implode(',', $types)],
            'value' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $type   = WafRuleType::from($validated['type']);
        $action = in_array($type, [WafRuleType::IpAllow], true) ? 'allow' : 'block';

        WafRule::create([
            'service_id' => $service->id,
            'created_by' => $request->user()?->id,
            'type'       => $type,
            'value'      => trim((string) $validated['value']),
            'action'     => $action,
            'notes'      => $validated['notes'] ?? null,
            'is_active'  => true,
        ]);

        activity('security')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['type' => $type->value, 'value' => $validated['value']])
            ->log('waf.rule_created');

        return back()->with('status', 'WAF pravidlo přidáno.');
    }

    public function destroy(Request $request, Service $service, WafRule $rule): RedirectResponse
    {
        $this->authorize('view', $service);
        abort_if($rule->service_id !== $service->id, 403);

        $rule->delete();

        activity('security')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['rule_id' => $rule->id])
            ->log('waf.rule_deleted');

        return back()->with('status', 'Pravidlo odstraněno.');
    }

    public function toggle(Request $request, Service $service, WafRule $rule): RedirectResponse
    {
        $this->authorize('view', $service);
        abort_if($rule->service_id !== $service->id, 403);

        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('status', $rule->is_active ? 'Pravidlo aktivováno.' : 'Pravidlo deaktivováno.');
    }
}
