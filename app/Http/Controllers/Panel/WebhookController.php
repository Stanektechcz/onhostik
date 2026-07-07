<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\OutgoingWebhook;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    private const ALLOWED_EVENTS = [
        'service.created',
        'service.status_changed',
        'service.suspended',
        'service.terminated',
        'invoice.created',
        'invoice.paid',
        'invoice.overdue',
        'monitor.down',
        'monitor.up',
        'ticket.created',
        'ticket.replied',
        'ticket.closed',
        '*',
    ];

    private const MAX_WEBHOOKS = 10;

    public function index(Request $request): View
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        $webhooks = OutgoingWebhook::query()
            ->where('customer_id', $customer->id)
            ->withCount('deliveries')
            ->orderByDesc('created_at')
            ->get();

        return view('panel.webhooks', [
            'webhooks'      => $webhooks,
            'allowedEvents' => self::ALLOWED_EVENTS,
            'maxWebhooks'   => self::MAX_WEBHOOKS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        if (OutgoingWebhook::query()->where('customer_id', $customer->id)->count() >= self::MAX_WEBHOOKS) {
            return back()->withErrors(['name' => 'Dosažen maximální počet webhooků (' . self::MAX_WEBHOOKS . ').']);
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'url'      => ['required', 'url', 'max:500'],
            'events'   => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(self::ALLOWED_EVENTS)],
            'secret'   => ['nullable', 'string', 'max:255'],
        ]);

        OutgoingWebhook::create([
            'customer_id' => $customer->id,
            'name'        => $validated['name'],
            'url'         => $validated['url'],
            'events'      => $validated['events'],
            'secret'      => $validated['secret'] ?? '',
            'is_active'   => true,
        ]);

        return back()->with('status', 'Webhook byl vytvořen.');
    }

    public function toggle(Request $request, OutgoingWebhook $webhook): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $webhook->customer_id !== $customer->id, 403);

        $webhook->update(['is_active' => ! $webhook->is_active]);

        $label = $webhook->is_active ? 'aktivován' : 'deaktivován';

        return back()->with('status', "Webhook byl {$label}.");
    }

    public function destroy(Request $request, OutgoingWebhook $webhook): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $webhook->customer_id !== $customer->id, 403);

        $webhook->delete();

        return back()->with('status', 'Webhook byl smazán.');
    }

    public function deliveries(Request $request, OutgoingWebhook $webhook): View
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $webhook->customer_id !== $customer->id, 403);

        $deliveries = $webhook->deliveries()
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('panel.webhook-deliveries', compact('webhook', 'deliveries'));
    }
}
