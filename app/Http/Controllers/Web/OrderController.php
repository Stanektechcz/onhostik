<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Shared\Enums\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Public order entry point referenced by the pricing cards.
     * Shows the plan summary and hands over to the client-zone checkout
     * (panel.orders.create) — guests go through login/registration first.
     */
    public function start(Request $request, PricingPlan $plan): View
    {
        // Plans/products taken off sale (e.g. gamehosting until Pterodactyl
        // ships) must 404 here, not just disappear from the picker UI.
        abort_if(! $plan->is_active || ! $plan->product?->is_active, 404);

        $currency = $request->user()?->customer?->preferred_currency ?? Currency::default();

        return view('front.order', [
            'plan'     => $plan->load('product'),
            'currency' => $plan->supportsCurrency($currency) ? $currency : Currency::CZK,
        ]);
    }
}
