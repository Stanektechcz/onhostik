<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CreateAdHocInvoiceAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Notifications\InvoiceIssuedNotification;
use Brick\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

// ── CreateAdHocInvoiceAction unit tests ───────────────────────────────────────

it('action creates invoice with correct totals for single item', function (): void {
    Notification::fake();
    $user = customerUser(['country_code' => 'CZ', 'vat_number' => null]);

    $invoice = app(CreateAdHocInvoiceAction::class)->execute(
        $user->customer,
        [['description' => 'Hosting Basic', 'quantity' => 1, 'unit_price_minor' => 10000, 'vat_rate' => 21.0]],
        Carbon::tomorrow(),
    );

    expect($invoice->type)->toBe(InvoiceType::Invoice)
        ->and($invoice->purpose)->toBe('adhoc')
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->vat_scenario)->toBe(VatScenario::CzechB2C)
        ->and($invoice->subtotal)->toBeInstanceOf(Money::class)
        ->and($invoice->subtotal->getMinorAmount()->toInt())->toBe(10000)
        ->and($invoice->tax_amount->getMinorAmount()->toInt())->toBe(2100)
        ->and($invoice->total->getMinorAmount()->toInt())->toBe(12100)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->description)->toBe('Hosting Basic');
});

it('action calculates totals correctly for multiple items', function (): void {
    Notification::fake();
    $user = customerUser(['country_code' => 'CZ', 'vat_number' => null]);

    $invoice = app(CreateAdHocInvoiceAction::class)->execute(
        $user->customer,
        [
            ['description' => 'Hosting', 'quantity' => 1, 'unit_price_minor' => 10000, 'vat_rate' => 21.0],
            ['description' => 'Domain',  'quantity' => 2, 'unit_price_minor' => 50000, 'vat_rate' => 21.0],
        ],
        Carbon::tomorrow(),
    );

    // subtotal = 10000 + 2*50000 = 110000 minor units
    expect($invoice->subtotal->getMinorAmount()->toInt())->toBe(110000)
        ->and($invoice->tax_amount->getMinorAmount()->toInt())->toBe(23100)
        ->and($invoice->total->getMinorAmount()->toInt())->toBe(133100)
        ->and($invoice->items)->toHaveCount(2);
});

it('action creates invoice with zero VAT for B2B reverse charge customer', function (): void {
    Notification::fake();
    $user = customerUser([
        'country_code'      => 'DE',
        'vat_number'        => 'DE123456789',
        'vat_validated_at'  => now(),
    ]);

    $invoice = app(CreateAdHocInvoiceAction::class)->execute(
        $user->customer,
        [['description' => 'Consulting', 'quantity' => 1, 'unit_price_minor' => 50000, 'vat_rate' => 0.0]],
        Carbon::tomorrow(),
    );

    expect($invoice->vat_scenario)->toBe(VatScenario::EuB2BReverseCharge)
        ->and($invoice->tax_amount->getMinorAmount()->toInt())->toBe(0)
        ->and($invoice->total->getMinorAmount()->toInt())->toBe(50000);
});

it('action sets billing snapshot from customer', function (): void {
    Notification::fake();
    $user = customerUser([
        'company_name' => 'Acme s.r.o.',
        'country_code' => 'CZ',
    ]);

    $invoice = app(CreateAdHocInvoiceAction::class)->execute(
        $user->customer,
        [['description' => 'Test', 'quantity' => 1, 'unit_price_minor' => 1000, 'vat_rate' => 21.0]],
        Carbon::tomorrow(),
    );

    expect($invoice->snapshot_company)->toBe('Acme s.r.o.')
        ->and($invoice->snapshot_country_code)->toBe('CZ');
});

it('action sends InvoiceIssuedNotification to customer', function (): void {
    Notification::fake();
    $user = customerUser();

    app(CreateAdHocInvoiceAction::class)->execute(
        $user->customer,
        [['description' => 'Service', 'quantity' => 1, 'unit_price_minor' => 5000, 'vat_rate' => 21.0]],
        Carbon::tomorrow(),
    );

    Notification::assertSentTo($user, InvoiceIssuedNotification::class);
});

it('action stores notes on invoice', function (): void {
    Notification::fake();
    $user = customerUser();

    $invoice = app(CreateAdHocInvoiceAction::class)->execute(
        $user->customer,
        [['description' => 'Service', 'quantity' => 1, 'unit_price_minor' => 1000, 'vat_rate' => 21.0]],
        Carbon::tomorrow(),
        'Poznámka pro zákazníka',
    );

    expect($invoice->notes)->toBe('Poznámka pro zákazníka');
});

// ── HTTP controller tests ─────────────────────────────────────────────────────

it('admin can view adhoc invoice creation form', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.invoices.adhoc-create'))
         ->assertOk()
         ->assertSee('Nová ad-hoc faktura')
         ->assertSee('Zákazník');
});

it('guest is redirected from adhoc invoice creation form', function (): void {
    $this->get(route('admin.invoices.adhoc-create'))
         ->assertRedirect();
});

it('admin can create adhoc invoice via POST', function (): void {
    Notification::fake();
    $admin = adminUser();
    $user  = customerUser();

    $this->actingAs($admin)
         ->post(route('admin.invoices.adhoc-store'), [
             'customer_id' => $user->customer->id,
             'due_date'    => now()->addDays(14)->format('Y-m-d'),
             'notes'       => 'Test',
             'items'       => [
                 ['description' => 'Hosting', 'quantity' => 1, 'unit_price_minor' => 9900, 'vat_rate' => '21'],
             ],
         ])
         ->assertRedirect()
         ->assertSessionHas('status');

    $invoice = Invoice::query()->where('purpose', 'adhoc')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->customer_id)->toBe($user->customer->id)
        ->and($invoice->type)->toBe(InvoiceType::Invoice);
});

it('adhoc store validates missing items', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.invoices.adhoc-store'), [
             'customer_id' => customerUser()->customer->id,
             'due_date'    => now()->addDays(14)->format('Y-m-d'),
             'items'       => [],
         ])
         ->assertSessionHasErrors('items');
});

it('adhoc store validates due date must be in future', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.invoices.adhoc-store'), [
             'customer_id' => customerUser()->customer->id,
             'due_date'    => now()->subDay()->format('Y-m-d'),
             'items'       => [
                 ['description' => 'X', 'quantity' => 1, 'unit_price_minor' => 100, 'vat_rate' => '21'],
             ],
         ])
         ->assertSessionHasErrors('due_date');
});

it('invoice list page shows adhoc create button', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.invoices.index'))
         ->assertOk()
         ->assertSee('Nová faktura');
});
