<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookRetryPolicyController extends Controller
{
    public function index(): View
    {
        $endpoints = \Illuminate\Support\Facades\DB::table('webhook_endpoints')
            ->orderBy('name')
            ->get();

        return view('admin.webhook-retry-policy.index', compact('endpoints'));
    }

    public function update(Request $request, int $endpointId): RedirectResponse
    {
        $validated = $request->validate([
            'max_retries'         => ['required', 'integer', 'min:0', 'max:10'],
            'retry_delay_seconds' => ['required', 'integer', 'min:10', 'max:3600'],
            'timeout_seconds'     => ['required', 'integer', 'min:3', 'max:60'],
        ]);

        \Illuminate\Support\Facades\DB::table('webhook_endpoints')
            ->where('id', $endpointId)
            ->update($validated);

        return back()->with('status', 'Retry politika uložena.');
    }
}
