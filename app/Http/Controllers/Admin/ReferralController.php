<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\CustomerReferral;
use App\Domains\Customer\Services\ReferralService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referralService) {}

    public function index(Request $request): View
    {
        $status = $request->input('status');

        $referrals = CustomerReferral::with(['referrer.user', 'referee.user'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'total'    => CustomerReferral::count(),
            'pending'  => CustomerReferral::where('status', 'pending')->count(),
            'rewarded' => CustomerReferral::where('status', 'rewarded')->count(),
        ];

        return view('admin.referrals.index', compact('referrals', 'stats', 'status'));
    }

    public function qualify(CustomerReferral $referral): RedirectResponse
    {
        $this->referralService->qualify($referral);

        return back()->with('status', 'Referral byl kvalifikován.');
    }

    public function reward(CustomerReferral $referral): RedirectResponse
    {
        $this->referralService->reward($referral);

        return back()->with('status', 'Odměny byly vyplaceny.');
    }

    public function expire(CustomerReferral $referral): RedirectResponse
    {
        $this->referralService->expire($referral);

        return back()->with('status', 'Referral byl označen jako vypršelý.');
    }
}
