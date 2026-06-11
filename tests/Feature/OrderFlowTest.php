<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\IssueProformaInvoiceAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Products\Models\PricingPlan;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('creates an order with a pricing snapshot and issues the proforma invoice', function (): void {
    $user = customerUser();
    $plan = PricingPlan::query()->orderBy('sort_order')->firstOrFail(); // Start — 4 900 minor CZK

    $response = $this->actingAs($user)->post('/panel/objednavky', [
        'pricing_plan_id' => $plan->id,
        'domain'          => 'muj-novy-web.cz',
        'register_domain' => '1',
    ]);

    $order = Order::firstOrFail();
    $response->assertRedirect(route('panel.orders.show', $order));

    // Pricing snapshot: 4 900 net + 21 % Czech VAT = 5 929 gross.
    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->customer_id)->toBe($user->customer?->id)
        ->and($order->vat_scenario)->toBe('cz_b2c')
        ->and($order->subtotal->getMinorAmount()->toInt())->toBe(4_900)
        ->and($order->tax_amount->getMinorAmount()->toInt())->toBe(1_029)
        ->and($order->total->getMinorAmount()->toInt())->toBe(5_929)
        ->and($order->items)->toHaveCount(1);

    $item = $order->items->firstOrFail();
    expect($item->pricing_plan_id)->toBe($plan->id)
        ->and($item->config['domain'] ?? null)->toBe('muj-novy-web.cz')
        ->and($item->config['register_domain'] ?? null)->toBeTrue()
        ->and((float) $item->vat_rate)->toBe(21.0)
        ->and($item->period_from)->not->toBeNull()
        ->and($item->period_to)->not->toBeNull();

    $invoice = Invoice::firstOrFail();
    expect($invoice->type)->toBe(InvoiceType::Proforma)
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->number)->toStartWith('CZ-')
        ->and($invoice->order_id)->toBe($order->id)
        ->and($invoice->total->getMinorAmount()->toInt())->toBe(5_929)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->variable_symbol)->not->toBeNull()
        ->and($invoice->snapshot_name)->not->toBeNull()
        ->and($invoice->snapshot_country_code)->toBe('CZ');

    // Audit trail.
    expect(Activity::where('log_name', 'order')->where('description', 'order.created')->exists())->toBeTrue()
        ->and(Activity::where('log_name', 'invoice')->where('description', 'invoice.issued')->exists())->toBeTrue();
});

it('rejects an order with an unavailable domain', function (): void {
    $user = customerUser();
    $plan = PricingPlan::query()->orderBy('sort_order')->firstOrFail();

    $this->actingAs($user)
        ->from('/panel/objednavky/nova')
        ->post('/panel/objednavky', [
            'pricing_plan_id' => $plan->id,
            'domain'          => 'taken-web.cz',
            'register_domain' => '1',
        ])
        ->assertRedirect('/panel/objednavky/nova')
        ->assertSessionHasErrors('domain');

    expect(Order::count())->toBe(0)
        ->and(Invoice::count())->toBe(0);
});

it('never issues a second proforma for the same order', function (): void {
    $user = customerUser();
    $plan = PricingPlan::query()->orderBy('sort_order')->firstOrFail();

    $this->actingAs($user)->post('/panel/objednavky', ['pricing_plan_id' => $plan->id]);

    $order  = Order::firstOrFail();
    $action = app(IssueProformaInvoiceAction::class);

    $first  = $action->execute($order);
    $second = $action->execute($order->refresh());

    expect(Invoice::count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});
