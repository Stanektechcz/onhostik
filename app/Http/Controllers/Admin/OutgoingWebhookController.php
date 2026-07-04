<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutgoingWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutgoingWebhookController extends Controller
{
    public const ALLOWED_EVENTS = [
        '*',
        'service.provisioned',
        'service.failed',
        'invoice.paid',
        'ticket.created',
        'domain.registered',
        'domain.expiring',
    ];

    public function index(): View
    {
        return view('admin.webhooks.index', [
            'webhooks' => OutgoingWebhook::withCount('deliveries')->latest()->get(),
            'recentDeliveries' => WebhookDelivery::latest()->limit(20)->get(),
            'deliveredCount' => WebhookDelivery::where('status', 'delivered')->count(),
            'failedCount'    => WebhookDelivery::where('status', 'failed')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.webhooks.create', [
            'allowedEvents' => self::ALLOWED_EVENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'url'       => ['required', 'url', 'max:500'],
            'secret'    => ['nullable', 'string', 'max:64'],
            'events'    => ['required', 'array', 'min:1'],
            'events.*'  => ['string', 'in:' . implode(',', self::ALLOWED_EVENTS)],
            'is_active' => ['boolean'],
        ]);

        OutgoingWebhook::create([
            'name'      => $validated['name'],
            'url'       => $validated['url'],
            'secret'    => $validated['secret'] ?? '',
            'events'    => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('admin.outgoing-webhooks.index')
            ->with('status', 'Webhook byl přidán.');
    }

    public function destroy(OutgoingWebhook $outgoingWebhook): RedirectResponse
    {
        $outgoingWebhook->delete();

        return redirect()
            ->route('admin.outgoing-webhooks.index')
            ->with('status', 'Webhook byl odstraněn.');
    }

    public function deliveries(OutgoingWebhook $outgoingWebhook): View
    {
        return view('admin.webhooks.deliveries', [
            'webhook'    => $outgoingWebhook,
            'deliveries' => $outgoingWebhook->deliveries()->latest()->paginate(30),
        ]);
    }
}
