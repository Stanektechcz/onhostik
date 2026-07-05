<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Automation\Models\AutomationRuleLog;
use App\Domains\Automation\Services\AutomationEngine;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function __construct(private readonly AutomationEngine $engine) {}

    public function index(): View
    {
        return view('admin.automation.index', [
            'rules' => AutomationRule::orderBy('trigger')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.automation.form', [
            'rule'     => new AutomationRule(),
            'triggers' => self::triggers(),
            'actions'  => self::actions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        AutomationRule::create($data);

        return redirect()->route('admin.automation.index')
            ->with('status', 'Pravidlo bylo vytvořeno.');
    }

    public function edit(AutomationRule $rule): View
    {
        return view('admin.automation.form', [
            'rule'     => $rule,
            'triggers' => self::triggers(),
            'actions'  => self::actions(),
        ]);
    }

    public function update(Request $request, AutomationRule $rule): RedirectResponse
    {
        $data = $this->validated($request);
        $rule->update($data);

        return redirect()->route('admin.automation.index')
            ->with('status', 'Pravidlo bylo uloženo.');
    }

    public function destroy(AutomationRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('admin.automation.index')
            ->with('status', 'Pravidlo bylo smazáno.');
    }

    public function toggle(AutomationRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('status', $rule->is_active ? 'Pravidlo aktivováno.' : 'Pravidlo deaktivováno.');
    }

    public function logs(AutomationRule $rule): View
    {
        return view('admin.automation.logs', [
            'rule' => $rule,
            'logs' => AutomationRuleLog::where('automation_rule_id', $rule->id)
                ->orderByDesc('id')
                ->paginate(50),
        ]);
    }

    public function testFire(Request $request, AutomationRule $rule): RedirectResponse
    {
        $context = $request->input('context', []);
        if (is_string($context)) {
            $context = json_decode($context, true) ?? [];
        }

        $this->engine->fire($rule->trigger, array_merge($context, ['_test' => true]));

        return back()->with('status', "Pravidlo bylo spuštěno testovacím kontextem. Zkontrolujte logy.");
    }

    /** @return array<string, string> */
    public static function triggers(): array
    {
        return [
            'ticket.created'      => 'Ticket vytvořen',
            'ticket.replied'      => 'Ticket — nová odpověď',
            'ticket.closed'       => 'Ticket uzavřen',
            'invoice.overdue'     => 'Faktura po splatnosti',
            'invoice.paid'        => 'Faktura zaplacena',
            'service.expiring'    => 'Služba brzy vyprší',
            'service.suspended'   => 'Služba suspendována',
            'customer.registered' => 'Nový zákazník',
            'partner.apply'       => 'Partnerská přihláška',
        ];
    }

    /** @return array<string, string> */
    public static function actions(): array
    {
        return [
            'send_notification' => 'Odeslat notifikaci (e-mail)',
            'log_event'         => 'Zapsat do logu',
            'webhook_call'      => 'Volat webhook URL',
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:150'],
            'trigger'            => ['required', 'string', 'max:80'],
            'action'             => ['required', 'string', 'max:80'],
            'is_active'          => ['nullable', 'boolean'],
            'conditions_json'    => ['nullable', 'string'],
            'action_params_json' => ['nullable', 'string'],
        ]);

        return [
            'name'          => $data['name'],
            'trigger'       => $data['trigger'],
            'action'        => $data['action'],
            'is_active'     => $request->boolean('is_active', true),
            'conditions'    => ! empty($data['conditions_json'])
                                    ? json_decode((string) $data['conditions_json'], true)
                                    : null,
            'action_params' => ! empty($data['action_params_json'])
                                    ? json_decode((string) $data['action_params_json'], true)
                                    : null,
        ];
    }
}
