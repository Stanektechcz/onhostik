<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\DomainTransferRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainTransferRequestController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $requests = DomainTransferRequest::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('panel.domain-transfer-requests.index', compact('requests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $validated = $request->validate([
            'domain_name' => 'required|max:255',
            'auth_code'   => 'nullable|max:255',
        ]);

        DomainTransferRequest::create(array_merge($validated, [
            'customer_id' => $customerId,
            'status'      => 'pending',
        ]));

        return back()->with('success', 'Žádost o přenos domény odeslána.');
    }
}
