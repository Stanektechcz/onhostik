<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Models\HelpdeskWebhook;
use App\Domains\Support\Models\HelpdeskWebhookDelivery;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HelpdeskWebhookController extends Controller
{
    public function index(): View
    {
        $webhooks  = HelpdeskWebhook::orderBy('name')->get();
        $recentLog = HelpdeskWebhookDelivery::with('webhook')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('admin.helpdesk-webhooks.index', compact('webhooks', 'recentLog'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'url'             => ['required', 'url', 'max:500'],
            'secret'          => ['nullable', 'string', 'max:100'],
            'events'          => ['required', 'array', 'min:1'],
            'events.*'        => [Rule::in(array_keys(HelpdeskWebhook::EVENTS))],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'is_active'       => ['boolean'],
        ]);

        HelpdeskWebhook::create([
            'name'            => $data['name'],
            'url'             => $data['url'],
            'secret'          => !empty($data['secret']) ? $data['secret'] : null,
            'events'          => $data['events'],
            'timeout_seconds' => $data['timeout_seconds'] ?? 10,
            'is_active'       => $data['is_active'] ?? true,
        ]);

        return redirect()->route('admin.helpdesk-webhooks.index')
            ->with('success', 'Webhook byl vytvořen.');
    }

    public function update(Request $request, HelpdeskWebhook $helpdeskWebhook): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'url'             => ['required', 'url', 'max:500'],
            'secret'          => ['nullable', 'string', 'max:100'],
            'events'          => ['required', 'array', 'min:1'],
            'events.*'        => [Rule::in(array_keys(HelpdeskWebhook::EVENTS))],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'is_active'       => ['boolean'],
        ]);

        $helpdeskWebhook->update([
            'name'            => $data['name'],
            'url'             => $data['url'],
            'secret'          => !empty($data['secret']) ? $data['secret'] : null,
            'events'          => $data['events'],
            'timeout_seconds' => $data['timeout_seconds'] ?? 10,
            'is_active'       => !empty($data['is_active']),
        ]);

        return redirect()->route('admin.helpdesk-webhooks.index')
            ->with('success', 'Webhook byl aktualizován.');
    }

    public function destroy(HelpdeskWebhook $helpdeskWebhook): RedirectResponse
    {
        $helpdeskWebhook->delete();

        return redirect()->route('admin.helpdesk-webhooks.index')
            ->with('success', 'Webhook byl smazán.');
    }

    public function regenerateSecret(HelpdeskWebhook $helpdeskWebhook): RedirectResponse
    {
        $helpdeskWebhook->update(['secret' => Str::random(40)]);

        return redirect()->route('admin.helpdesk-webhooks.index')
            ->with('success', 'Nový secret byl vygenerován.');
    }
}
