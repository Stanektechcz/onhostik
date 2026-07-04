<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Reseller\Models\ResellerPricingOverride;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResellerPricingController extends Controller
{
    public function index(ResellerProfile $reseller): View
    {
        $reseller->load('user');
        $overrides = ResellerPricingOverride::where('reseller_id', $reseller->id)
            ->with('pricingPlan')
            ->get();

        $plans = PricingPlan::with('product')->orderBy('id')->get();

        return view('admin.resellers.pricing', compact('reseller', 'overrides', 'plans'));
    }

    public function store(Request $request, ResellerProfile $reseller): RedirectResponse
    {
        $validated = $request->validate([
            'pricing_plan_id' => ['required', 'integer', 'exists:pricing_plans,id'],
            'price_czk'       => ['nullable', 'integer', 'min:0'],
            'price_eur'       => ['nullable', 'integer', 'min:0'],
            'price_usd'       => ['nullable', 'integer', 'min:0'],
        ]);

        ResellerPricingOverride::updateOrCreate(
            [
                'reseller_id'     => $reseller->id,
                'pricing_plan_id' => $validated['pricing_plan_id'],
            ],
            [
                'price_czk' => $validated['price_czk'] ?? null,
                'price_eur' => $validated['price_eur'] ?? null,
                'price_usd' => $validated['price_usd'] ?? null,
                'is_active' => true,
            ]
        );

        return back()->with('status', 'Cenový override byl uložen.');
    }

    public function destroy(ResellerProfile $reseller, ResellerPricingOverride $override): RedirectResponse
    {
        abort_if($override->reseller_id !== $reseller->id, 404);

        $override->delete();

        return back()->with('status', 'Cenový override byl smazán.');
    }
}
