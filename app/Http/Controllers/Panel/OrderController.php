<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Actions\CreateOrderAction;
use App\Domains\Billing\Actions\IssueProformaInvoiceAction;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Services\DriverResolver;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        $counts = \Illuminate\Support\Facades\DB::table('orders')
            ->where('customer_id', $customer->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('panel.orders.index', [
            'orders'         => $customer->orders()->with('items')->latest('id')->paginate(15),
            'countTotal'     => (int) $counts->sum(),
            'countActive'    => (int) ($counts[\App\Domains\Billing\Enums\OrderStatus::Active->value] ?? 0),
            'countPending'   => (int) ($counts[\App\Domains\Billing\Enums\OrderStatus::Pending->value] ?? 0),
            'countCancelled' => (int) ($counts[\App\Domains\Billing\Enums\OrderStatus::Cancelled->value] ?? 0),
        ]);
    }

    public function create(Request $request): View
    {
        $plans = PricingPlan::query()
            ->where('pricing_plans.is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with('product')
            ->join('products', 'pricing_plans.product_id', '=', 'products.id')
            ->orderBy('products.sort_order')
            ->orderBy('pricing_plans.sort_order')
            ->select('pricing_plans.*')
            ->get();

        return view('panel.orders.create', [
            'plans'        => $plans,
            'selectedPlan' => $request->integer('plan') ?: null,
            'customer'     => $this->customer($request),
            'mockMode'     => (bool) config('provisioning.mock_mode', true),
        ]);
    }

    public function store(
        Request $request,
        CreateOrderAction $createOrder,
        IssueProformaInvoiceAction $issueProforma,
        DriverResolver $drivers,
    ): RedirectResponse {
        $validated = $request->validate([
            'pricing_plan_id'  => ['required', 'integer', 'exists:pricing_plans,id'],
            'domain'           => ['nullable', 'string', 'min:3', 'max:253', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.[a-zA-Z]{2,}$/'],
            'register_domain'  => ['nullable', 'boolean'],
            'simulate_failure' => ['nullable', 'boolean'],
        ]);

        $customer = $this->customer($request);

        $plan = PricingPlan::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->findOrFail((int) $validated['pricing_plan_id']);

        $domain         = isset($validated['domain']) && is_string($validated['domain']) ? $validated['domain'] : null;
        $registerDomain = $domain !== null && $request->boolean('register_domain');

        // UX pre-check — the action re-validates inside the transaction.
        if ($registerDomain) {
            $check = $drivers->registrar()->checkDomain($domain);

            if (!$check->available) {
                return back()
                    ->withInput()
                    ->withErrors(['domain' => __('panel.orders.domain_unavailable', ['reason' => (string) $check->reason])]);
            }
        }

        try {
            $order = $createOrder->execute($customer, $plan, [
                'domain'          => $domain,
                'register_domain' => $registerDomain,
                // Only honoured in mock mode — never forwarded otherwise.
                'simulate_failure' => (bool) config('provisioning.mock_mode', true) && $request->boolean('simulate_failure'),
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['pricing_plan_id' => $e->getMessage()]);
        }

        $issueProforma->execute($order);

        return redirect()
            ->route('panel.orders.show', $order)
            ->with('status', __('panel.orders.created'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        return view('panel.orders.show', [
            'order' => $order->load(['items.pricingPlan', 'invoices.payments']),
        ]);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
