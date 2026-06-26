<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Enums\PayoutStatus;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerPayout;
use App\Domains\Partner\Models\PartnerProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

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

    public function create(Request $request): View
    {
        $existingPartnerUserIds = PartnerProfile::pluck('user_id')->toArray();

        $users = User::whereNotIn('id', $existingPartnerUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Support ?user_id= pre-fill from customer detail shortcut
        $prefilledUserId = $request->integer('user_id') ?: null;

        return view('admin.partners.form', [
            'partner'         => null,
            'users'           => $users,
            'defaultRate'     => config('partner.default_commission_rate_percent', 10.0),
            'prefilledUserId' => $prefilledUserId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'                 => ['required', 'exists:users,id', 'unique:partner_profiles,user_id'],
            'referral_code'           => ['required', 'string', 'min:4', 'max:16', 'regex:/^[A-Z0-9]+$/', 'unique:partner_profiles,referral_code'],
            'status'                  => ['required', 'in:active,paused,banned'],
            'commission_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_method'           => ['nullable', 'string', 'max:100'],
            'payout_details'          => ['nullable', 'string', 'max:1000'],
        ]);

        $partner = PartnerProfile::create([
            'user_id'                  => $validated['user_id'],
            'referral_code'            => strtoupper($validated['referral_code']),
            'status'                   => $validated['status'],
            'commission_rate_percent'  => (float) $validated['commission_rate_percent'],
            'payout_method'            => $validated['payout_method'] ?? null,
            'payout_details_encrypted' => isset($validated['payout_details']) && $validated['payout_details'] !== ''
                ? Crypt::encryptString($validated['payout_details'])
                : null,
        ]);

        // Grant access-partner permission to the user
        $user = User::findOrFail($validated['user_id']);
        Permission::findOrCreate('access-partner', 'web');
        $user->givePermissionTo('access-partner');

        activity('partner')
            ->performedOn($partner)
            ->causedBy($request->user())
            ->withProperties(['referral_code' => $partner->referral_code, 'status' => $partner->status->value])
            ->log('partner.created');

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('status', "Partner profil byl vytvořen. Referral kód: {$partner->referral_code}");
    }

    public function edit(PartnerProfile $partner): View
    {
        $partner->load('user');

        return view('admin.partners.form', [
            'partner'     => $partner,
            'users'       => null,
            'defaultRate' => $partner->commission_rate_percent,
        ]);
    }

    public function update(Request $request, PartnerProfile $partner): RedirectResponse
    {
        $validated = $request->validate([
            'referral_code'           => ['required', 'string', 'min:4', 'max:16', 'regex:/^[A-Z0-9]+$/', "unique:partner_profiles,referral_code,{$partner->id}"],
            'status'                  => ['required', 'in:active,paused,banned'],
            'commission_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_method'           => ['nullable', 'string', 'max:100'],
            'payout_details'          => ['nullable', 'string', 'max:1000'],
        ]);

        $prevStatus = $partner->status->value;

        $partner->update([
            'referral_code'            => strtoupper($validated['referral_code']),
            'status'                   => $validated['status'],
            'commission_rate_percent'  => (float) $validated['commission_rate_percent'],
            'payout_method'            => $validated['payout_method'] ?? null,
            'payout_details_encrypted' => isset($validated['payout_details']) && $validated['payout_details'] !== ''
                ? Crypt::encryptString($validated['payout_details'])
                : $partner->payout_details_encrypted,
        ]);

        activity('partner')
            ->performedOn($partner)
            ->causedBy($request->user())
            ->withProperties([
                'previous_status' => $prevStatus,
                'new_status'      => $validated['status'],
                'rate'            => $validated['commission_rate_percent'],
            ])
            ->log('partner.updated');

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('status', 'Partner profil byl aktualizován.');
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

        $approvedUnpaidMinor = (int) $partner->commissions()
            ->where('status', CommissionStatus::Approved->value)
            ->sum('amount');

        $counts = [
            'referrals'          => $partner->referrals()->count(),
            'customers'          => $partner->referrals()->where('status', 'customer')->count(),
            'commissionPending'  => $partner->commissions()->where('status', CommissionStatus::Pending->value)->sum('amount'),
            'commissionApproved' => $approvedUnpaidMinor,
            'commissionPaid'     => $partner->commissions()->where('status', CommissionStatus::Paid->value)->sum('amount'),
        ];

        return view('admin.partners.show', [
            'partner'             => $partner,
            'referrals'           => $referrals,
            'commissions'         => $commissions,
            'payouts'             => $payouts,
            'counts'              => $counts,
            'approvedUnpaidMinor' => $approvedUnpaidMinor,
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
            'commission_ids'   => ['required', 'array', 'min:1'],
            'commission_ids.*' => ['integer'],
            'method'           => ['nullable', 'string', 'max:100'],
            'note'             => ['nullable', 'string', 'max:500'],
        ]);

        // Load only approved, unpaid (not yet in a payout) commissions belonging to this partner
        $commissions = PartnerCommission::where('partner_profile_id', $partner->id)
            ->where('status', CommissionStatus::Approved->value)
            ->whereNull('partner_payout_id')
            ->whereIn('id', $validated['commission_ids'])
            ->get();

        if ($commissions->isEmpty()) {
            return back()->withErrors(['commission_ids' => 'Žádné platné schválené provize nebyly nalezeny.']);
        }

        $amountMinor = (int) $commissions->sum('amount');

        $payout = PartnerPayout::create([
            'partner_profile_id' => $partner->id,
            'amount'             => $amountMinor,
            'currency'           => 'CZK',
            'status'             => PayoutStatus::Requested,
            'method'             => $validated['method'] ?? null,
            'admin_note'         => $validated['note'] ?? null,
            'requested_at'       => now(),
        ]);

        // Link selected commissions to this payout
        PartnerCommission::whereIn('id', $commissions->pluck('id'))
            ->update(['partner_payout_id' => $payout->id]);

        activity('partner')
            ->performedOn($payout)
            ->causedBy($request->user())
            ->withProperties([
                'amount'         => $amountMinor,
                'currency'       => 'CZK',
                'method'         => $validated['method'] ?? 'n/a',
                'admin'          => $request->user()?->email,
                'commission_ids' => $commissions->pluck('id')->toArray(),
                'commission_count' => $commissions->count(),
            ])
            ->log('payout.created');

        return back()->with('status', "Výplata vytvořena: {$commissions->count()} provizí, " . number_format($amountMinor / 100, 0, ',', ' ') . ' Kč.');
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

        // Only mark commissions linked to THIS payout as paid
        $affected = PartnerCommission::where('partner_payout_id', $payout->id)
            ->whereIn('status', [CommissionStatus::Approved->value])
            ->update(['status' => CommissionStatus::Paid->value, 'paid_at' => now()]);

        $commissionIds = PartnerCommission::where('partner_payout_id', $payout->id)
            ->pluck('id')
            ->toArray();

        activity('partner')
            ->performedOn($payout)
            ->causedBy($request->user())
            ->withProperties([
                'payout_id'           => $payout->id,
                'amount'              => $payout->amount,
                'currency'            => $payout->currency,
                'method'              => $payout->method ?? 'manual',
                'admin_id'            => $request->user()?->id,
                'admin'               => $request->user()?->email,
                'commission_ids'      => $commissionIds,
                'commissions_settled' => $affected,
            ])
            ->log('payout.paid');

        return back()->with('status', "Výplata označena jako zaplacená. Zúčtováno {$affected} provizí.");
    }

    public function cancelPayout(Request $request, PartnerProfile $partner, PartnerPayout $payout): RedirectResponse
    {
        if ($payout->partner_profile_id !== $partner->id) {
            abort(404);
        }

        if ($payout->status === PayoutStatus::Paid) {
            return back()->withErrors(['payout' => 'Zaplacenou výplatu nelze zrušit.']);
        }

        // Return linked commissions to approved state
        $affected = PartnerCommission::where('partner_payout_id', $payout->id)
            ->update(['partner_payout_id' => null]);

        $payout->update([
            'status'     => PayoutStatus::Cancelled,
            'admin_note' => ($payout->admin_note ? $payout->admin_note . '; ' : '') . 'Zrušeno adminem.',
        ]);

        activity('partner')
            ->performedOn($payout)
            ->causedBy($request->user())
            ->withProperties(['commissions_released' => $affected])
            ->log('payout.cancelled');

        return back()->with('status', "Výplata zrušena. {$affected} provizí vráceno do stavu approved.");
    }

    /** Quick status toggle from detail page. */
    public function changeStatus(Request $request, PartnerProfile $partner): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,paused,banned'],
        ]);

        $prev = $partner->status->value;
        $partner->update(['status' => $validated['status']]);

        activity('partner')
            ->performedOn($partner)
            ->causedBy($request->user())
            ->withProperties(['previous_status' => $prev, 'new_status' => $validated['status']])
            ->log('partner.status_changed');

        return back()->with('status', 'Stav partnera byl změněn.');
    }

    /** Generate a unique referral code suggestion for the create form. */
    public function generateCode(): \Illuminate\Http\JsonResponse
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (PartnerProfile::where('referral_code', $code)->exists());

        return response()->json(['code' => $code]);
    }
}
