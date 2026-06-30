<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Products\Models\Product;
use App\Domains\Shared\Enums\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $product = Product::query()
            ->where('slug', 'webhosting')
            ->where('is_active', true)
            ->with(['pricingPlans' => fn ($query) => $query->where('is_active', true)])
            ->first();

        $customer = $request->user()?->customer;

        return view('front.home', [
            'product'  => $product,
            'plans'    => $product->pricingPlans ?? collect(),
            'currency' => $customer !== null ? $customer->preferred_currency : Currency::default(),
        ]);
    }
}
