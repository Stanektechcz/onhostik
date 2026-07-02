<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Products\Models\PricingPlan;
use App\Http\Controllers\Controller;
use App\Domains\Customer\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $wishlist = is_array(session('panel_wishlist')) ? session('panel_wishlist') : [];
        $customer = $this->customer($request);
        $plans    = PricingPlan::whereIn('id', $wishlist)
            ->where('is_active', true)
            ->with('product')
            ->get()
            ->keyBy('id');

        $items = collect($wishlist)->map(function (int $id) use ($plans, $customer) {
            $plan = $plans->get($id);
            if (! $plan) {
                return null;
            }
            $price    = $plan->priceFor($customer->preferred_currency);
            $priceVal = intdiv($price->getMinorAmount()->toInt(), 100);
            return [
                'plan'     => $plan,
                'price'    => $priceVal,
                'currency' => $price->getCurrency()->getCurrencyCode(),
                'prodName' => $plan->product->name ?: '',
                'fullName' => trim($plan->product->name . ' ' . $plan->name),
            ];
        })->filter()->values();

        return view('panel.wishlist.index', compact('items', 'customer'));
    }

    public function add(Request $request, int $plan): RedirectResponse
    {
        $wishlist = is_array(session('panel_wishlist')) ? session('panel_wishlist') : [];
        if (! in_array($plan, $wishlist, true)) {
            $wishlist[] = $plan;
            session(['panel_wishlist' => $wishlist]);
        }
        return back()->with('status', 'Tarif přidán do oblíbených.');
    }

    public function remove(Request $request, int $plan): RedirectResponse
    {
        $wishlist = array_values(array_filter(session('panel_wishlist', []), fn ($id) => $id !== $plan));
        session(['panel_wishlist' => $wishlist]);
        return back()->with('status', 'Tarif odebrán z oblíbených.');
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403, 'No customer profile.');
        return $customer;
    }
}
