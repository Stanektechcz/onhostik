<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\PayoutStatus;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerPayout;
use App\Domains\Partner\Models\PartnerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(): View
    {
        $partners = PartnerProfile::with('user')
            ->withCount(['referrals', 'commissions'])
            ->withSum(['commissions as pending_sum' => fn ($q) => $q->where('status', CommissionStatus::Pending->value)], 'amount')
            ->withSum(['commissions as approved_sum' => fn ($q) => $q->where('status', CommissionStatus::Approved->value)], 'amount')
            ->latest()
            ->paginate(25);

        return view('admin.partners.index', [
            'partners'    => $partners,
            'totalCount'  => PartnerProfile::count(),
            'activeCount' => PartnerProfile::where('status', 'active')->count(),
        ]);
    }

    public function show(PartnerProfile $partner): View
    {
        $partner->load(['user', 'user.customer']);

        $referrals = $partner->referrals()
            ->with('referredUser', 'referredCustomer')
            ->latest()
            ->paginate(15, pageName: 'ref_page');

        $commissions = $partner->commissions()
            ->with('invoice', 'order')
            ->latest()
            ->paginate(15, pageName: 'com_page');

        $payouts = $partner->payouts()
            ->latest()
            ->paginate(10, pageName: 'pay_page');

        $counts = [
            'referrals'         => $partner->referrals()->count(),
            'customers'         => $partner->referrals()->where('status', 'customer')->count(),
            'commissionPending' => $partner->commissions()->where('status', CommissionStatus::Pending->value)->sum('amount'),
            'commissionApproved' => $partner->commissions()->where('status', CommissionStatus::Approved->value)->sum('amount'),
            'commissionPaid'    => $partner->commissions()->where('status', CommissionStatus::Paid->value)->sum('amount'),
        ];

        return view('admin.partners.show', [
            'partner'     => $partner,
            'referrals'   => $referrals,
            'commissions' => $commissions,
            'payouts'     => $payouts,
            'counts'      => $counts,
        ]);
    }

    public function approveCommission(Request $request, PartnerProfile $partner, PartnerCommission $commission): RedirectResponse
    {
        if ($commission->partner_profile_id !== $partner->id) {
            abort(404);
        }

        if ($commission->status !== CommissionStatus::Pending) {
            return back()->withErrors(['commission' => 'Pouze čekající provize lze schválit.']);
        }

        $commission->update([
            'status'      => CommissionStatus::Approved,
            'approved_at' => now(),
        ]);

        activity('partner')
            ->performedOn($commission)
            ->causedBy($request->user())
            ->withProperties(['previous_status' => CommissionStatus::Pending->value])
            ->log('commission.approved');

        return back()->with('status', 'Provize byla schválena.');
    }

    public function rejectCommission(Request $request, PartnerProfile $partner, PartnerCommission $commission): RedirectResponse
    {
        if ($commission->partner_profile_id !== $partner->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($commission->status->isFinal()) {
            return back()->withErrors(['commission' => 'Provize je již ve finálním stavu.']);
        }

        $commission->update([
            'status' => CommissionStatus::Rejected,
            'notes'  => $validated['reason'] ?? null,
        ]);

        activity('partner')
            ->performedOn($commission)
            ->causedBy($request->user())
            ->withProperties(['reason' => $validated['reason'] ?? ''])
            ->log('commission.rejected');

        return back()->with('status', 'Provize byla zamítnuta.');
    }

    public function createPayout(Request $request, PartnerProfile $partner): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', 'string', 'max:100'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $payout = PartnerPayout::create([
            'partner_profile_id' => $partner->id,
            'amount'             => (int) $validated['amount'] * 100, // expect CZK whole units in form
            'currency'           => 'CZK',
            'status'             => PayoutStatus::Requested,
            'method'             => $validated['method'] ?? null,
            'admin_note'         => $validated['note'] ?? null,
            'requested_at'       => now(),
        ]);

        activity('partner')
            ->performedOn($payout)
            ->causedBy($request->user())
            ->withProperties(['amount' => $payout->amount])
            ->log('payout.created');

        return back()->with('status', 'Výplata byla vytvořena.');
    }

    public function markPayoutPaid(Request $request, PartnerProfile $partner, PartnerPayout $payout): RedirectResponse
    {
        if ($payout->partner_profile_id !== $partner->id) {
            abort(404);
        }

        if ($payout->status === PayoutStatus::Paid) {
            return back()->withErrors(['payout' => 'Výplata je již zaplacena.']);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payout->update([
            'status'       => PayoutStatus::Paid,
            'processed_at' => now(),
            'admin_note'   => $validated['note'] ?? $payout->admin_note,
        ]);

        // Mark related approved commissions as paid
        PartnerCommission::where('partner_profile_id', $partner->id)
            ->where('status', CommissionStatus::Approved->value)
            ->update(['status' => CommissionStatus::Paid->value, 'paid_at' => now()]);

        activity('partner')
            ->performedOn($payout)
            ->causedBy($request->user())
            ->withProperties(['amount' => $payout->amount])
            ->log('payout.paid');

        return back()->with('status', 'Výplata byla označena jako zaplacená.');
    }
}
