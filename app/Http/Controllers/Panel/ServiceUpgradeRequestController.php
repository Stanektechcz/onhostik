<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ServiceUpgradeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceUpgradeRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = ServiceUpgradeRequest::where('user_id', $request->user()->id)
            ->with(['service'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('panel.service-upgrade-requests.index', compact('requests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id'        => 'required|integer',
            'requested_plan_id' => 'nullable|integer',
            'customer_note'     => 'nullable|string|max:1000',
        ]);

        ServiceUpgradeRequest::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status'  => 'pending',
        ]);

        return back()->with('status', 'Žádost o upgrade odeslána.');
    }

    public function show(Request $request, ServiceUpgradeRequest $serviceUpgradeRequest): View
    {
        abort_unless($serviceUpgradeRequest->user_id === $request->user()->id, 403);

        $serviceUpgradeRequest->load('service');

        return view('panel.service-upgrade-requests.show', compact('serviceUpgradeRequest'));
    }
}
