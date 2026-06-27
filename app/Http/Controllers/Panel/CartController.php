<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Products\Models\PricingPlan;
use App\Http\Controllers\Controller;
use App\Domains\Customer\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart     = session('panel_cart', []);
        $planIds  = array_keys($cart);
        $plans    = PricingPlan::whereIn('id', $planIds)->with('product')->get()->keyBy('id');
        $customer = $this->customer($request);

        $items = collect($planIds)->map(function (int $id) use ($plans, $cart, $customer) {
            $plan = $plans->get($id);
            if (! $plan) {
                return null;
            }
            $price    = $plan->priceFor($customer->preferred_currency);
            $priceVal = $price ? intdiv($price->getMinorAmount()->toInt(), 100) : 0;
            return [
                'plan'     => $plan,
                'qty'      => (int) ($cart[$id] ?? 1),
                'price'    => $priceVal,
                'currency' => $price?->getCurrency()->getCurrencyCode() ?? 'CZK',
                'subtotal' => $priceVal * (int) ($cart[$id] ?? 1),
            ];
        })->filter()->values();

        $total = $items->sum('subtotal');

        return view('panel.cart.index', compact('items', 'total', 'customer'));
    }

    public function add(Request $request, int $plan): RedirectResponse
    {
        $cart         = session('panel_cart', []);
        $cart[$plan]  = ($cart[$plan] ?? 0) + 1;
        session(['panel_cart' => $cart]);

        return back()->with('status', 'Tarif přidán do košíku.');
    }

    public function remove(Request $request, int $plan): RedirectResponse
    {
        $cart = session('panel_cart', []);
        unset($cart[$plan]);
        session(['panel_cart' => $cart]);

        return back()->with('status', 'Tarif odebrán z košíku.');
    }

    public function clear(): RedirectResponse
    {
        session()->forget('panel_cart');
        return redirect()->route('panel.cart.index');
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403, 'No customer profile.');
        return $customer;
    }
}
