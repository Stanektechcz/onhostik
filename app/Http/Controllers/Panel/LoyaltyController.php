<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Loyalty\Services\LoyaltyService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyaltyService) {}

    public function index(Request $request): View
    {
        $customer = $request->user()->customer;
        $rewards  = $customer->loyaltyRewards()->with('milestone')->latest('awarded_at')->get();
        $next     = $this->loyaltyService->nextMilestone($customer);

        return view('panel.loyalty.index', compact('rewards', 'next'));
    }
}
