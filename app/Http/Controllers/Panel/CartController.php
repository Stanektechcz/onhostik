<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Actions\CreateCartOrderAction;
use App\Domains\Billing\Actions\IssueProformaInvoiceAction;
use App\Domains\Billing\Actions\PayInvoiceWithCreditAction;
use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Session-backed cart: lets a customer collect several plans and order
 * them in ONE order (one proforma, one payment).
 *
 * Cart shape in session: [pricing_plan_id => qty].
 */
class CartController extends Controller
{
    private const SESSION_KEY = 'panel_cart';
    private const MAX_QTY     = 20;

    public function index(Request $request): View
    {
        $customer = $this->customer($request);
        $items    = $this->items($customer);

        return view('panel.cart.index', [
            'items'         => $items,
            'subtotal'      => $items->sum('subtotal'),
            'currency'      => $customer->preferred_currency->value,
            'customer'      => $customer,
            'creditBalance' => app(CreditLedger::class)->getBalance($customer),
        ]);
    }

    public function add(Request $request, int $plan): RedirectResponse
    {
        // Only orderable plans may enter the cart.
        $exists = PricingPlan::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->whereKey($plan)
            ->exists();

        if (! $exists) {
            return back()->withErrors(['cart' => 'Tento tarif nelze objednat.']);
        }

        $cart        = $this->cart();
        $cart[$plan] = min(self::MAX_QTY, ($cart[$plan] ?? 0) + 1);
        session([self::SESSION_KEY => $cart]);

        return back()->with('status', 'Tarif přidán do košíku.');
    }

    public function update(Request $request, int $plan): RedirectResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:' . self::MAX_QTY],
        ]);

        $cart = $this->cart();

        if ((int) $validated['qty'] === 0) {
            unset($cart[$plan]);
        } elseif (isset($cart[$plan])) {
            $cart[$plan] = (int) $validated['qty'];
        }

        session([self::SESSION_KEY => $cart]);

        return back()->with('status', 'Košík byl aktualizován.');
    }

    public function remove(Request $request, int $plan): RedirectResponse
    {
        $cart = $this->cart();
        unset($cart[$plan]);
        session([self::SESSION_KEY => $cart]);

        return back()->with('status', 'Tarif odebrán z košíku.');
    }

    public function clear(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('panel.cart.index')->with('status', 'Košík byl vyprázdněn.');
    }

    /** Order everything in the cart as a single order. */
    public function checkout(
        Request $request,
        CreateCartOrderAction $createOrder,
        IssueProformaInvoiceAction $issueProforma,
        PayInvoiceWithCreditAction $payWithCredit,
    ): RedirectResponse {
        $validated = $request->validate([
            'payment_method' => ['nullable', 'in:comgate,credit,bank'],
            'discount_code'  => ['nullable', 'string', 'max:50'],
            'domains'        => ['nullable', 'array'],
            'domains.*'      => ['nullable', 'string', 'max:253', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.[a-zA-Z]{2,}$/'],
        ]);

        $customer = $this->customer($request);
        $items    = $this->items($customer);

        if ($items->isEmpty()) {
            return redirect()->route('panel.cart.index')->withErrors(['cart' => 'Košík je prázdný.']);
        }

        /** @var array<int|string, string|null> $domains */
        $domains = $validated['domains'] ?? [];

        $reseller = $request->user()?->can('access-reseller')
            ? ResellerProfile::where('user_id', $request->user()->id)->where('status', 'active')->first()
            : null;

        try {
            $order = $createOrder->execute(
                $customer,
                $items->map(function (array $i) use ($domains): array {
                    $raw    = $domains[$i['plan']->id] ?? null;
                    $domain = is_string($raw) ? mb_strtolower(trim($raw)) : '';

                    return [
                        'plan'   => $i['plan'],
                        'qty'    => $i['qty'],
                        'config' => $domain !== '' ? ['domain' => $domain] : [],
                    ];
                })->all(),
                [
                    'discount_code'  => $validated['discount_code'] ?? null,
                    'markup_percent' => $reseller ? (float) $reseller->markup_percent : 0.0,
                ],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        $invoice = $issueProforma->execute($order);
        session()->forget(self::SESSION_KEY);

        if (($validated['payment_method'] ?? null) === 'credit') {
            try {
                $payWithCredit->execute($invoice);

                return redirect()->route('panel.orders.show', $order)
                    ->with('status', __('panel.orders.created_and_paid'));
            } catch (InsufficientCreditException) {
                return redirect()->route('panel.orders.show', $order)
                    ->with('warning', __('panel.orders.created_credit_insufficient'));
            }
        }

        return redirect()->route('panel.orders.show', $order)
            ->with('status', __('panel.orders.created'));
    }

    /** @return array<int, int> */
    private function cart(): array
    {
        /** @var array<int, int> $cart */
        $cart = session(self::SESSION_KEY, []);

        return $cart;
    }

    /**
     * Resolve the cart into priced lines, dropping plans that have since
     * been deactivated or deleted.
     *
     * @return \Illuminate\Support\Collection<int, array{plan: PricingPlan, qty: int, price: int, subtotal: int}>
     */
    private function items(Customer $customer): \Illuminate\Support\Collection
    {
        $cart = $this->cart();

        if ($cart === []) {
            return collect();
        }

        $plans = PricingPlan::query()
            ->whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with('product')
            ->get()
            ->keyBy('id');

        return collect($cart)
            ->map(function (int $qty, int $planId) use ($plans, $customer): ?array {
                $plan = $plans->get($planId);

                if ($plan === null || ! $plan->supportsCurrency($customer->preferred_currency)) {
                    return null;
                }

                $price = $plan->priceFor($customer->preferred_currency)->getMinorAmount()->toInt();

                return [
                    'plan'     => $plan,
                    'qty'      => $qty,
                    'price'    => $price,
                    'subtotal' => $price * $qty,
                ];
            })
            ->filter()
            ->values();
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile.');

        return $customer;
    }
}
