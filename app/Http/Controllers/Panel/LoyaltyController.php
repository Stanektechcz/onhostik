<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Loyalty\Models\LoyaltyReward;
use App\Domains\Loyalty\Services\LoyaltyPointsService;
use App\Domains\Loyalty\Services\LoyaltyService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly LoyaltyPointsService $points,
    ) {}

    public function index(Request $request): View
    {
        $customer = $request->user()->customer;
        $rewards  = $customer->loyaltyRewards()->with('milestone')->latest('awarded_at')->get();
        $next     = $this->loyaltyService->nextMilestone($customer);

        $pointsBalance = $this->points->balance($customer);
        $catalog       = LoyaltyReward::query()->where('is_active', true)->orderBy('points_cost')->get();

        return view('panel.loyalty.index', compact('rewards', 'next', 'pointsBalance', 'catalog'));
    }

    public function redeem(Request $request, LoyaltyReward $reward): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($customer === null, 403);

        if (! $this->points->redeem($customer, $reward)) {
            return back()->withErrors(['redeem' => 'Nedostatek bodů nebo odměna není dostupná.']);
        }

        return back()->with('status', "Odměna „{$reward->name}“ byla uplatněna a kredit připsán.");
    }
}
