<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Actions\RecalculateOrderTotalsAction;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Models\OrderItem;
use App\Domains\Products\Models\PricingPlan;
use App\Http\Controllers\Controller;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Line-item editing on an order (audit G95).
 *
 * Admins could previously only change an order's status, note and per-line
 * domain — not add a line, remove one, or correct a price. Anything else
 * meant editing the database by hand.
 *
 * Every change recomputes the order header, otherwise the invoiced amount
 * would drift from the lines.
 *
 * Guard: an order that has already been invoiced/paid is NOT editable here —
 * changing the amount after the fact would desync the accounting. Use a
 * credit note instead. This is a data-integrity rule, not a permission one.
 */
class OrderItemController extends Controller
{
    public function store(Request $request, Order $order, RecalculateOrderTotalsAction $recalculate): RedirectResponse
    {
        if ($error = $this->immutable($order)) {
            return back()->withErrors(['items' => $error]);
        }

        $validated = $request->validate([
            'pricing_plan_id' => ['required', 'integer', 'exists:pricing_plans,id'],
            'quantity'        => ['required', 'integer', 'min:1', 'max:100'],
            'unit_price'      => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'description'     => ['nullable', 'string', 'max:255'],
        ]);

        $plan     = PricingPlan::with('product')->findOrFail((int) $validated['pricing_plan_id']);
        $currency = $order->currency;

        if (! $plan->supportsCurrency($currency)) {
            return back()->withErrors(['items' => "Tarif nemá cenu v měně {$currency->value}."]);
        }

        $unitPrice = isset($validated['unit_price'])
            ? Money::of($validated['unit_price'], $currency->value)
            : $plan->priceFor($currency);

        $quantity = (int) $validated['quantity'];

        $order->items()->create([
            'pricing_plan_id'     => $plan->id,
            'description'         => $validated['description']
                ?? trim(($plan->product->name ?? 'Položka') . ' ' . $plan->name),
            'quantity'            => $quantity,
            'currency'            => $currency->value,
            'unit_price'          => $unitPrice,
            'vat_rate'            => $order->items->first()->vat_rate ?? 0,
            'total'               => $unitPrice->multipliedBy($quantity, RoundingMode::HALF_UP),
            'period_from'         => now()->startOfDay(),
            'period_to'           => now()->startOfDay()->addMonths($plan->billing_cycle->months()),
            'provisioning_status' => \App\Domains\Provisioning\Enums\TaskStatus::Pending,
            'config'              => [],
        ]);

        $recalculate->execute($order);

        $this->log($request, $order, 'order.item_added', ['plan_id' => $plan->id, 'quantity' => $quantity]);

        return back()->with('status', 'Položka byla přidána a objednávka přepočítána.');
    }

    public function update(Request $request, Order $order, OrderItem $item, RecalculateOrderTotalsAction $recalculate): RedirectResponse
    {
        if ($error = $this->immutable($order)) {
            return back()->withErrors(['items' => $error]);
        }

        abort_if($item->order_id !== $order->id, 404);

        $validated = $request->validate([
            'quantity'    => ['required', 'integer', 'min:1', 'max:100'],
            'unit_price'  => ['required', 'numeric', 'min:0', 'max:10000000'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $unitPrice = Money::of($validated['unit_price'], $order->currency->value);
        $quantity  = (int) $validated['quantity'];

        $item->update([
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total'       => $unitPrice->multipliedBy($quantity, RoundingMode::HALF_UP),
            'description' => $validated['description'] ?? $item->description,
        ]);

        $recalculate->execute($order);

        $this->log($request, $order, 'order.item_updated', ['item_id' => $item->id, 'quantity' => $quantity]);

        return back()->with('status', 'Položka byla upravena a objednávka přepočítána.');
    }

    public function destroy(Request $request, Order $order, OrderItem $item, RecalculateOrderTotalsAction $recalculate): RedirectResponse
    {
        if ($error = $this->immutable($order)) {
            return back()->withErrors(['items' => $error]);
        }

        abort_if($item->order_id !== $order->id, 404);

        if ($order->items()->count() <= 1) {
            return back()->withErrors(['items' => 'Objednávka musí mít alespoň jednu položku — místo smazání ji zrušte.']);
        }

        // A provisioned line has a real service behind it; removing the line
        // would orphan that service. (Service keys to order_item_id.)
        if (\App\Domains\Provisioning\Models\Service::where('order_item_id', $item->id)->exists()) {
            return back()->withErrors(['items' => 'Položka už má zřízenou službu — nejprve zrušte službu.']);
        }

        $item->delete();

        $recalculate->execute($order);

        $this->log($request, $order, 'order.item_removed', ['item_id' => $item->id]);

        return back()->with('status', 'Položka byla odebrána a objednávka přepočítána.');
    }

    /** Why this order may not be line-edited, or null when it may. */
    private function immutable(Order $order): ?string
    {
        if ($order->paid_at !== null) {
            return 'Zaplacenou objednávku nelze měnit — vystavte dobropis.';
        }

        if (in_array($order->status, [OrderStatus::Active, OrderStatus::Cancelled], true)) {
            return 'Upravovat lze jen objednávku, která ještě není aktivní ani zrušená.';
        }

        return null;
    }

    /** @param array<string, mixed> $properties */
    private function log(Request $request, Order $order, string $event, array $properties): void
    {
        activity('order')
            ->performedOn($order)
            ->causedBy($request->user())
            ->withProperties($properties + ['total' => $order->fresh()?->total?->getMinorAmount()->toInt()])
            ->log($event);
    }
}
