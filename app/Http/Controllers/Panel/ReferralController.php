<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\CustomerReferral;
use App\Domains\Customer\Services\ReferralService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referralService) {}

    public function index(Request $request): View
    {
        $customer = $request->user()->customer;

        $code     = $this->referralService->getOrCreateCode($customer);
        $stats    = $this->referralService->stats($customer);

        $referrals = CustomerReferral::where('referrer_id', $customer->id)
            ->with('referee.user')
            ->orderByDesc('created_at')
            ->get();

        return view('panel.referrals.index', compact('code', 'stats', 'referrals'));
    }
}
