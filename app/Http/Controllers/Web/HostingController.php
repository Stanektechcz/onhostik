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

    public function wordpress(Request $request): View
    {
        $product = Product::query()
            ->where('slug', 'webhosting')
            ->where('is_active', true)
            ->with(['pricingPlans' => fn ($query) => $query->where('is_active', true)])
            ->first();

        return view('front.wordpress-hosting', [
            'plans'    => $product->pricingPlans ?? collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function managed(Request $request): View
    {
        $product = Product::query()
            ->where('slug', 'managed-hosting')
            ->where('is_active', true)
            ->with(['pricingPlans' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->first();

        return view('front.managed-hosting', [
            'product'  => $product,
            'plans'    => $product?->pricingPlans ?? collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function gamehosting(Request $request): View
    {
        $product = Product::query()
            ->where('slug', 'gamehosting')
            ->where('is_active', true)
            ->with(['pricingPlans' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->first();

        return view('front.gamehosting', [
            'product'  => $product,
            'plans'    => $product?->pricingPlans ?? collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function vps(Request $request): View
    {
        $product = Product::query()
            ->where('slug', 'vps')
            ->where('is_active', true)
            ->with(['pricingPlans' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->first();

        return view('front.vps', [
            'product'  => $product,
            'plans'    => $product?->pricingPlans ?? collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function mailhosting(Request $request): View
    {
        $product = Product::query()
            ->where('slug', 'mailhosting')
            ->where('is_active', true)
            ->with(['pricingPlans' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->first();

        return view('front.mailhosting', [
            'product'  => $product,
            'plans'    => $product?->pricingPlans ?? collect(),
            'currency' => $this->displayCurrency($request),
        ]);
    }

    public function dedicated(): View
    {
        return view('front.dedicated');
    }

    /** Logged-in customers see their billing currency; guests see CZK. */
    private function displayCurrency(Request $request): Currency
    {
        return $request->user()?->customer?->preferred_currency ?? Currency::default();
    }
}
