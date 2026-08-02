<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Products\Models\Product;
use App\Domains\Shared\Enums\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HostingController extends Controller
{
    public function webhosting(Request $request): View
    {
        $product = $this->catalogProduct('webhosting');

        return view('front.webhosting', [
            'product'  => $product,
            'plans'    => $product->pricingPlans ?? collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function wordpress(Request $request): View
    {
        $product = $this->catalogProduct('webhosting');

        return view('front.wordpress-hosting', [
            'plans'    => $product->pricingPlans ?? collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function managed(Request $request): View
    {
        $product = $this->catalogProduct('managed-hosting');

        return view('front.managed-hosting', [
            'product'  => $product,
            'plans'    => $product !== null ? $product->pricingPlans : collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function gamehosting(Request $request): View
    {
        $product = $this->catalogProduct('gamehosting');

        return view('front.gamehosting', [
            'product'  => $product,
            'plans'    => $product !== null ? $product->pricingPlans : collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function vps(Request $request): View
    {
        $product = $this->catalogProduct('vps');

        return view('front.vps', [
            'product'  => $product,
            'plans'    => $product !== null ? $product->pricingPlans : collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function mailhosting(Request $request): View
    {
        $product = $this->catalogProduct('mailhosting');

        return view('front.mailhosting', [
            'product'  => $product,
            'plans'    => $product !== null ? $product->pricingPlans : collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function dedicated(): View
    {
        return view('front.dedicated');
    }

    /** Logged-in customers see their billing currency; guests see CZK. */
    /**
     * Active catalog product with its active pricing plans, cached (audit 500
     * #17). These are the highest-traffic public pages and the catalog changes
     * rarely — re-querying product + plans on every visit is pure waste. The
     * cache is invalidated by the Product/PricingPlan observers on save.
     */
    private function catalogProduct(string $slug): ?Product
    {
        return Cache::remember(
            'catalog:product:' . $slug,
            600,
            fn (): ?Product => Product::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->with(['pricingPlans' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                ->first(),
        );
    }

    private function displayCurrency(Request $request): Currency
    {
        $customer = $request->user()?->customer;
        return $customer !== null ? $customer->preferred_currency : Currency::default();
    }
}
