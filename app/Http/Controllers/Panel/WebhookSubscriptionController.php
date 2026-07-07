<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CustomerWebhookSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebhookSubscriptionController extends Controller
{
    private const AVAILABLE_EVENTS = [
        'invoice.paid',
        'invoice.overdue',
        'service.created',
        'service.suspended',
        'service.terminated',
        'ticket.replied',
        'payment.received',
    ];

    public function index(Request $request): View
    {
        $customer = $request->user()->customer;
        abort_if($customer === null, 403);

        $subscriptions = CustomerWebhookSubscription::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        return view('panel.webhooks.index', [
            'subscriptions'    => $subscriptions,
            'availableEvents'  => self::AVAILABLE_EVENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($customer === null, 403);

        $validated = $request->validate([
            'url'    => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'in:' . implode(',', self::AVAILABLE_EVENTS)],
        ]);

        CustomerWebhookSubscription::create([
            'customer_id' => $customer->id,
            'url'         => $validated['url'],
            'secret'      => Str::random(32),
            'events'      => $validated['events'],
            'is_active'   => true,
        ]);

        return back()->with('status', 'Webhook přihlášení vytvořeno.');
    }

    public function destroy(Request $request, CustomerWebhookSubscription $subscription): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($subscription->customer_id !== $customer?->id, 403);

        $subscription->delete();

        return back()->with('status', 'Webhook odstraněn.');
    }
}
