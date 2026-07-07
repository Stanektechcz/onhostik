<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use App\Models\ResellerPayoutRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResellerPayoutRequestController extends Controller
{
    public function index(Request $request): View
    {
        $reseller = ResellerProfile::where('user_id', $request->user()->id)->first();
        abort_unless($reseller !== null, 403);

        $requests = ResellerPayoutRequest::where('reseller_profile_id', $reseller->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('panel.reseller-payout-requests.index', compact('requests', 'reseller'));
    }

    public function store(Request $request): RedirectResponse
    {
        $reseller = ResellerProfile::where('user_id', $request->user()->id)->first();
        abort_unless($reseller !== null, 403);

        $validated = $request->validate([
            'amount'   => 'required|integer|min:100',
            'currency' => 'nullable|string|max:3',
            'note'     => 'nullable|string|max:500',
        ]);

        ResellerPayoutRequest::create([
            'reseller_profile_id' => $reseller->id,
            'amount'              => $validated['amount'],
            'currency'            => $validated['currency'] ?? 'CZK',
            'status'              => 'pending',
            'requested_by'        => $request->user()->id,
            'note'                => $validated['note'] ?? null,
        ]);

        return back()->with('status', 'Žádost o výplatu podána.');
    }
}
