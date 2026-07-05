<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Domains\Loyalty\Models\CustomerLoyaltyReward;
use App\Domains\Loyalty\Models\LoyaltyMilestone;
use App\Domains\Loyalty\Services\LoyaltyService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(private readonly LoyaltyService $loyaltyService) {}

    public function index(): View
    {
        $milestones    = LoyaltyMilestone::withCount('rewards')->orderBy('sort_order')->get();
        $recentRewards = CustomerLoyaltyReward::with(['customer.user', 'milestone'])
            ->latest('awarded_at')
            ->limit(20)
            ->get();

        return view('admin.loyalty.index', compact('milestones', 'recentRewards'));
    }

    public function create(): View
    {
        return view('admin.loyalty.form', ['milestone' => new LoyaltyMilestone()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'slug'          => ['required', 'string', 'max:100', 'unique:loyalty_milestones,slug'],
            'trigger_type'  => ['required', 'in:account_age_days,order_count,total_spent_czk'],
            'trigger_value' => ['required', 'integer', 'min:1'],
            'reward_type'   => ['required', 'in:credit_czk,badge,discount_percent'],
            'reward_value'  => ['required', 'integer', 'min:1'],
            'description'   => ['nullable', 'string', 'max:500'],
            'is_active'     => ['boolean'],
            'sort_order'    => ['integer', 'min:0'],
        ]);

        LoyaltyMilestone::create($validated + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.loyalty.index')->with('status', 'Milník vytvořen.');
    }

    public function edit(LoyaltyMilestone $milestone): View
    {
        return view('admin.loyalty.form', compact('milestone'));
    }

    public function update(Request $request, LoyaltyMilestone $milestone): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'slug'          => ['required', 'string', 'max:100', 'unique:loyalty_milestones,slug,' . $milestone->id],
            'trigger_type'  => ['required', 'in:account_age_days,order_count,total_spent_czk'],
            'trigger_value' => ['required', 'integer', 'min:1'],
            'reward_type'   => ['required', 'in:credit_czk,badge,discount_percent'],
            'reward_value'  => ['required', 'integer', 'min:1'],
            'description'   => ['nullable', 'string', 'max:500'],
            'is_active'     => ['boolean'],
            'sort_order'    => ['integer', 'min:0'],
        ]);

        $milestone->update($validated + ['is_active' => $request->boolean('is_active', false)]);

        return redirect()->route('admin.loyalty.index')->with('status', 'Milník upraven.');
    }

    public function destroy(LoyaltyMilestone $milestone): RedirectResponse
    {
        $milestone->delete();

        return redirect()->route('admin.loyalty.index')->with('status', 'Milník smazán.');
    }

    public function checkCustomer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $count    = $this->loyaltyService->checkAndAward($customer);

        return back()->with('status', "Zkontrolováno. Uděleno nových milníků: {$count}.");
    }
}
