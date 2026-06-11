<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Products\Models\Product;
use App\Domains\Shared\Enums\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HostingController extends Controller
{
    public function webhosting(Request $request): View
    {
        $product = Product::query()
            ->where('slug', 'webhosting')
            ->where('is_active', true)
            ->with(['pricingPlans' => fn ($query) => $query->where('is_active', true)])
            ->first();

        return view('front.webhosting', [
            'product'  => $product,
            'plans'    => $product->pricingPlans ?? collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function gamehosting(): View
    {
        return view('front.gamehosting');
    }

    public function vps(): View
    {
        return view('front.vps');
    }

    /** Logged-in customers see their billing currency; guests see CZK. */
    private function displayCurrency(Request $request): Currency
    {
        return $request->user()->customer->preferred_currency ?? Currency::default();
    }
}
