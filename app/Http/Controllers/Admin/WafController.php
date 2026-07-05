<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Security\Enums\WafRuleType;
use App\Domains\Security\Models\WafEvent;
use App\Domains\Security\Models\WafRule;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WafController extends Controller
{
    public function index(): View
    {
        $rules = WafRule::query()
            ->with(['service', 'creator'])
            ->latest('id')
            ->paginate(30);

        $recentEvents = WafEvent::query()
            ->with('service')
            ->orderByDesc('blocked_at')
            ->limit(20)
            ->get();

        return view('admin.waf.index', compact('rules', 'recentEvents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $types = array_column(WafRuleType::cases(), 'value');

        $validated = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'type'       => ['required', 'string', 'in:' . implode(',', $types)],
            'value'      => ['required', 'string', 'max:100'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $type   = WafRuleType::from($validated['type']);
        $action = $type === WafRuleType::IpAllow ? 'allow' : 'block';

        WafRule::create([
            'service_id' => $validated['service_id'] ?? null,
            'created_by' => $request->user()?->id,
            'type'       => $type,
            'value'      => trim((string) $validated['value']),
            'action'     => $action,
            'notes'      => $validated['notes'] ?? null,
            'is_active'  => true,
        ]);

        return back()->with('status', 'WAF pravidlo přidáno.');
    }

    public function destroy(WafRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', 'Pravidlo odstraněno.');
    }

    public function toggle(WafRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('status', $rule->is_active ? 'Pravidlo aktivováno.' : 'Pravidlo deaktivováno.');
    }
}
