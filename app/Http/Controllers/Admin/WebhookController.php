<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Integration\Models\InboundWebhookLog;
use App\Domains\Integration\Models\WebhookEndpoint;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function index(Request $request): View
    {
        $source = $request->input('source');
        $status = $request->input('status');

        $logs = InboundWebhookLog::query()
            ->when($source, fn ($q) => $q->where('source', $source))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $sources = InboundWebhookLog::query()->distinct()->pluck('source');
        $endpoints = WebhookEndpoint::orderBy('name')->get();

        return view('admin.webhooks.inbound', compact('logs', 'sources', 'endpoints', 'source', 'status'));
    }

    public function show(InboundWebhookLog $log): View
    {
        return view('admin.webhooks.inbound-show', compact('log'));
    }

    public function endpointCreate(): View
    {
        return view('admin.webhooks.endpoint-form', ['endpoint' => new WebhookEndpoint()]);
    }

    public function endpointStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'source'           => ['required', 'string', 'max:50', 'unique:webhook_endpoints,source'],
            'secret'           => ['nullable', 'string', 'max:255'],
            'signature_algo'   => ['required', 'in:sha256,sha512,sha1'],
            'signature_header' => ['required', 'string', 'max:100'],
            'allowed_events'   => ['nullable', 'string'],
            'description'      => ['nullable', 'string', 'max:500'],
            'is_active'        => ['boolean'],
        ]);

        $validated['allowed_events'] = !empty($validated['allowed_events'])
            ? array_filter(array_map('trim', explode("\n", $validated['allowed_events'])))
            : null;

        // api_key never logged (only 'secret_set: true' is safe to log)
        WebhookEndpoint::create($validated);

        return redirect()->route('admin.webhooks.inbound.index')
            ->with('status', 'Webhook endpoint byl přidán.');
    }

    public function endpointEdit(WebhookEndpoint $endpoint): View
    {
        return view('admin.webhooks.endpoint-form', compact('endpoint'));
    }

    public function endpointUpdate(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'secret'           => ['nullable', 'string', 'max:255'],
            'signature_algo'   => ['required', 'in:sha256,sha512,sha1'],
            'signature_header' => ['required', 'string', 'max:100'],
            'allowed_events'   => ['nullable', 'string'],
            'description'      => ['nullable', 'string', 'max:500'],
            'is_active'        => ['boolean'],
        ]);

        $validated['allowed_events'] = !empty($validated['allowed_events'])
            ? array_filter(array_map('trim', explode("\n", $validated['allowed_events'])))
            : null;

        // Keep existing secret if field was left blank
        if (empty($validated['secret'])) {
            unset($validated['secret']);
        }

        $endpoint->update($validated);

        return redirect()->route('admin.webhooks.inbound.index')
            ->with('status', 'Webhook endpoint byl upraven.');
    }

    public function endpointDestroy(WebhookEndpoint $endpoint): RedirectResponse
    {
        $endpoint->delete();

        return redirect()->route('admin.webhooks.inbound.index')
            ->with('status', 'Webhook endpoint byl odstraněn.');
    }

    public function endpointToggle(WebhookEndpoint $endpoint): RedirectResponse
    {
        $endpoint->update(['is_active' => !$endpoint->is_active]);

        return back()->with('status', 'Stav endpointu byl změněn.');
    }
}
