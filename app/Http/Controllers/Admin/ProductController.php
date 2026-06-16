<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Products\Enums\BillingCycle;
use App\Domains\Products\Enums\ProductType;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products', [
            'products' => Product::query()
                ->with(['pricingPlans' => fn ($query) => $query->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.product-form', [
            'product'  => null,
            'types'    => ProductType::cases(),
            'drivers'  => ProvisioningDriver::cases(),
            'cycles'   => BillingCycle::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        $product = Product::create([
            'slug'                => $validated['slug'],
            'type'                => $validated['type'],
            'name'                => ['cs' => $validated['name_cs'], 'en' => $validated['name_en'] ?: $validated['name_cs']],
            'description'         => ['cs' => $validated['description_cs'] ?? '', 'en' => $validated['description_en'] ?? ''],
            'provisioning_driver' => $validated['provisioning_driver'] ?: null,
            'is_active'           => $request->boolean('is_active'),
            'sort_order'          => (int) ($validated['sort_order'] ?? 0),
        ]);

        activity('product')
            ->performedOn($product)
            ->causedBy($request->user())
            ->withProperties(['type' => $product->type->value])
            ->log('product.created');

        return redirect()->route('admin.products.index')->with('status', __('panel.admin.plan_updated'));
    }

    public function edit(Product $product): View
    {
        return view('admin.product-form', [
            'product'  => $product->load('pricingPlans'),
            'types'    => ProductType::cases(),
            'drivers'  => ProvisioningDriver::cases(),
            'cycles'   => BillingCycle::cases(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product->id);

        $product->update([
            'slug'                => $validated['slug'],
            'type'                => $validated['type'],
            'name'                => ['cs' => $validated['name_cs'], 'en' => $validated['name_en'] ?: $validated['name_cs']],
            'description'         => ['cs' => $validated['description_cs'] ?? '', 'en' => $validated['description_en'] ?? ''],
            'provisioning_driver' => $validated['provisioning_driver'] ?: null,
            'is_active'           => $request->boolean('is_active'),
            'sort_order'          => (int) ($validated['sort_order'] ?? 0),
        ]);

        activity('product')
            ->performedOn($product)
            ->causedBy($request->user())
            ->withProperties(['type' => $product->type->value])
            ->log('product.updated');

        return redirect()->route('admin.products.edit', $product)->with('status', 'Produkt byl uložen.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        if ($product->pricingPlans()->exists()) {
            return back()->withErrors(['product' => 'Nelze smazat produkt s existujícími plány.']);
        }

        activity('product')
            ->performedOn($product)
            ->causedBy($request->user())
            ->withProperties(['slug' => $product->slug])
            ->log('product.deleted');

        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Produkt byl smazán.');
    }

    /** Add a new pricing plan to a product. */
    public function addPlan(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name_cs'       => ['required', 'string', 'max:100'],
            'tagline_cs'    => ['nullable', 'string', 'max:200'],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'price_czk'     => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_eur'     => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'is_active'     => ['nullable', 'boolean'],
            'is_featured'   => ['nullable', 'boolean'],
        ]);

        $maxSort = $product->pricingPlans()->max('sort_order') ?? 0;

        $plan = PricingPlan::create([
            'product_id'    => $product->id,
            'name'          => ['cs' => $validated['name_cs'], 'en' => $validated['name_cs']],
            'tagline'       => ['cs' => $validated['tagline_cs'] ?? '', 'en' => ''],
            'billing_cycle' => $validated['billing_cycle'],
            'price_czk'     => isset($validated['price_czk']) ? (int) round((float) $validated['price_czk'] * 100) : null,
            'price_eur'     => isset($validated['price_eur']) ? (int) round((float) $validated['price_eur'] * 100) : null,
            'is_active'     => $request->boolean('is_active', true),
            'is_featured'   => $request->boolean('is_featured'),
            'sort_order'    => $maxSort + 1,
        ]);

        activity('product')
            ->performedOn($plan)
            ->causedBy($request->user())
            ->log('product.plan_added');

        return redirect()->route('admin.products.edit', $product)->with('status', 'Plán byl přidán.');
    }

    /** Remove a pricing plan (only if no order items reference it). */
    public function deletePlan(Request $request, PricingPlan $plan): RedirectResponse
    {
        $productId = $plan->product_id;

        if ($plan->orderItems()->exists()) {
            return back()->withErrors(['plan' => 'Nelze smazat plán s existujícími objednávkami.']);
        }

        activity('product')
            ->performedOn($plan)
            ->causedBy($request->user())
            ->withProperties(['name' => $plan->name])
            ->log('product.plan_deleted');

        $plan->delete();

        return redirect()->route('admin.products.edit', $productId)->with('status', 'Plán byl smazán.');
    }

    /** Basic plan management: prices (major units in form), flags. */
    public function updatePlan(Request $request, PricingPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'price_czk'   => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_eur'   => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'is_active'   => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $plan->update([
            'price_czk'   => isset($validated['price_czk']) ? (int) round((float) $validated['price_czk'] * 100) : $plan->price_czk,
            'price_eur'   => isset($validated['price_eur']) ? (int) round((float) $validated['price_eur'] * 100) : $plan->price_eur,
            'is_active'   => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
        ]);

        activity('product')
            ->performedOn($plan)
            ->causedBy($request->user())
            ->withProperties(['price_czk' => $plan->price_czk, 'is_active' => $plan->is_active])
            ->log('product.plan_updated');

        return back()->with('status', __('panel.admin.plan_updated'));
    }

    /** @return array<string, mixed> */
    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug'                => ['required', 'string', 'max:80', Rule::unique('products', 'slug')->ignore($ignoreId)],
            'type'                => ['required', Rule::enum(ProductType::class)],
            'name_cs'             => ['required', 'string', 'max:100'],
            'name_en'             => ['nullable', 'string', 'max:100'],
            'description_cs'      => ['nullable', 'string', 'max:1000'],
            'description_en'      => ['nullable', 'string', 'max:1000'],
            'provisioning_driver' => ['nullable', Rule::enum(ProvisioningDriver::class)],
            'is_active'           => ['nullable', 'boolean'],
            'sort_order'          => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
