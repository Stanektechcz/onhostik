<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Products\Models\PricingPlan;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    /**
     * Public order entry point referenced by pricing cards.
     * The real checkout flow arrives in Phase 4 — this shows a
     * plan summary placeholder and points to the client zone.
     */
    public function start(PricingPlan $plan): View
    {
        return view('front.order', ['plan' => $plan]);
    }
}
