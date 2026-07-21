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

        $subtotal = (int) $items->sum('subtotal');
        $vatRate  = app(\App\Domains\Billing\Services\VatResolver::class)->resolveRate($customer);
        $tax      = (int) round($subtotal * $vatRate / 100);

        // Recurring cost grouped by billing cycle (the shown price IS the
        // renewal price), so the customer sees what they pay each period.
        $recurring = $items
            ->groupBy(fn (array $i): string => $i['plan']->billing_cycle->value)
            ->map(fn ($group): array => [
                'label'  => $group->first()['plan']->billing_cycle->label(),
                'amount' => (int) $group->sum('subtotal'),
            ])
            ->values();

        $creditBalance = app(CreditLedger::class)->getBalance($customer);
        $total         = $subtotal + $tax;

        // C43: show the reseller their configured markup, if any.
        $resellerMarkup = 0.0;
        if ($request->user()?->can('access-reseller')) {
            $profile = ResellerProfile::where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->first();
            $resellerMarkup = $profile !== null ? (float) $profile->markup_percent : 0.0;
        }

        return view('panel.cart.index', [
            'items'          => $items,
            'subtotal'       => $subtotal,
            'vatRate'        => $vatRate,
            'tax'            => $tax,
            'total'          => $total,
            'recurring'      => $recurring,
            'currency'       => $customer->preferred_currency->value,
            'customer'       => $customer,
            'creditBalance'  => $creditBalance,
            'creditCovers'   => $creditBalance->getMinorAmount()->toInt() >= $total,
            'resellerMarkup' => $resellerMarkup,
        ]);
    }

    public function add(Request $request, int $plan): RedirectResponse
    {
        // Only orderable plans may enter the cart.
        $planModel = PricingPlan::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with('product')
            ->whereKey($plan)
            ->first();

        if ($planModel === null) {
            return back()->withErrors(['cart' => 'Tento tarif nelze objednat.']);
        }

        $cart = $this->cart();
        $cap  = $this->maxQtyFor($planModel);

        if ($cap === 1 && ($cart[$plan] ?? 0) >= 1) {
            return back()->with('status', 'Tuto službu lze objednat jen jednou — pro další instanci vytvořte novou objednávku.');
        }

        $cart[$plan] = min($cap, ($cart[$plan] ?? 0) + 1);
        session([self::SESSION_KEY => $cart]);

        return back()->with('status', 'Tarif přidán do košíku.');
    }

    public function update(Request $request, int $plan): RedirectResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:' . self::MAX_QTY],
        ]);

        $cart = $this->cart();
        $qty  = (int) $validated['qty'];

        if ($qty === 0) {
            unset($cart[$plan]);
        } elseif (isset($cart[$plan])) {
            $planModel   = PricingPlan::with('product')->find($plan);
            $cap         = $planModel !== null ? $this->maxQtyFor($planModel) : self::MAX_QTY;
            $cart[$plan] = min($qty, $cap);
        }

        session([self::SESSION_KEY => $cart]);

        return back()->with('status', 'Košík byl aktualizován.');
    }

    /**
     * Upper bound on how many of a plan may sit in the cart. Provisioning
     * products (web/VPS/game/domain) create one instance per order item, so
     * they are capped at 1 — a second instance is a separate order line.
     */
    private function maxQtyFor(PricingPlan $plan): int
    {
        return $plan->product?->provisionsInstance() === true ? 1 : self::MAX_QTY;
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
        \App\Domains\Provisioning\Services\DriverResolver $drivers,
    ): RedirectResponse {
        $validated = $request->validate([
            'payment_method'    => ['nullable', 'in:comgate,credit,bank'],
            'discount_code'     => ['nullable', 'string', 'max:50'],
            'domains'           => ['nullable', 'array'],
            'domains.*'         => ['nullable', 'string', 'max:253', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.[a-zA-Z]{2,}$/'],
            'register_domains'  => ['nullable', 'array'],
            'register_domains.*' => ['nullable', 'boolean'],
            'terms'             => ['accepted'], // C50: VOP + GDPR consent is mandatory
        ], [
            'terms.accepted' => 'Pro dokončení objednávky musíte souhlasit s obchodními podmínkami.',
        ]);

        $customer = $this->customer($request);
        $items    = $this->items($customer);

        if ($items->isEmpty()) {
            return redirect()->route('panel.cart.index')->withErrors(['cart' => 'Košík je prázdný.']);
        }

        // Validating the checkbox proves nothing later — GDPR art. 7(1) wants
        // the consent to be demonstrable, so record who agreed to which
        // document version, when and from where (audit H123).
        \App\Models\ConsentRecord::record($request, \App\Models\ConsentRecord::TYPE_TERMS, true, 'cart_checkout');
        \App\Models\ConsentRecord::record($request, \App\Models\ConsentRecord::TYPE_PRIVACY, true, 'cart_checkout');

        /** @var array<int|string, string|null> $domains */
        $domains = $validated['domains'] ?? [];
        /** @var array<int|string, mixed> $registerDomains */
        $registerDomains = $validated['register_domains'] ?? [];

        // UX pre-check: every domain flagged for NEW registration must be
        // available before we create the order (the job re-checks too).
        foreach ($domains as $planId => $raw) {
            $domain = is_string($raw) ? mb_strtolower(trim($raw)) : '';
            $wantsRegister = filter_var($registerDomains[$planId] ?? false, FILTER_VALIDATE_BOOL);

            if ($domain === '' || ! $wantsRegister) {
                continue;
            }

            $check = $drivers->registrar()->checkDomain($domain);

            if (! $check->available) {
                return back()->withInput()->withErrors([
                    'register_domains' => "Doménu {$domain} nelze registrovat: " . (string) $check->reason,
                ]);
            }
        }

        $reseller = $request->user()?->can('access-reseller')
            ? ResellerProfile::where('user_id', $request->user()->id)->where('status', 'active')->first()
            : null;

        try {
            $order = $createOrder->execute(
                $customer,
                $items->map(function (array $i) use ($domains, $registerDomains): array {
                    $raw    = $domains[$i['plan']->id] ?? null;
                    $domain = is_string($raw) ? mb_strtolower(trim($raw)) : '';
                    $register = $domain !== '' && filter_var($registerDomains[$i['plan']->id] ?? false, FILTER_VALIDATE_BOOL);

                    return [
                        'plan'   => $i['plan'],
                        'qty'    => $i['qty'],
                        'config' => $domain !== ''
                            ? array_filter(['domain' => $domain, 'register_domain' => $register])
                            : [],
                    ];
                })->all(),
                [
                    'discount_code'       => $validated['discount_code'] ?? null,
                    'markup_percent'      => $reseller ? (float) $reseller->markup_percent : 0.0,
                    // K146: individual per-plan prices take precedence over the
                    // blanket markup; the action resolves them from this id.
                    'reseller_profile_id' => $reseller?->id,
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
