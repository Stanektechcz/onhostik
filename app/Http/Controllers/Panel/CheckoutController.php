<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\PricingPlan;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        $planId = $request->integer('plan');
        $plan   = $planId ? PricingPlan::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with('product')
            ->find($planId) : null;

        $billingAddress = $customer->addresses()->where('type', 'billing')->first();
        $price = $plan?->priceFor($customer->preferred_currency);
        $priceVal = $price ? intdiv($price->getMinorAmount()->toInt(), 100) : 0;
        $currency = $price?->getCurrency()->getCurrencyCode() ?? 'CZK';
        $mockMode = (bool) config('provisioning.mock_mode', true);

        return view('panel.checkout', compact('customer', 'plan', 'billingAddress', 'priceVal', 'currency', 'mockMode'));
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403, 'No customer profile.');
        return $customer;
    }
}
