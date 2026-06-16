<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Models\Invoice;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
    Queue::fake();
});

it('downloads a proforma invoice PDF without error', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $response = $this->actingAs($user)
        ->get(route('panel.billing.invoices.print', $invoice));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('pdf');
});

it('downloads a paid tax document PDF', function (): void {
    $user = customerUser([
        'company_name' => 'PDF Test s.r.o.',
        'country_code' => 'CZ',
    ]);

    $user->customer->addresses()->create([
        'type'         => 'billing',
        'street'       => 'Tiskárna 1',
        'city'         => 'Praha',
        'zip'          => '11000',
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    ['invoice' => $proforma] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($proforma);

    $taxDoc = Invoice::query()
        ->where('type', InvoiceType::Invoice->value)
        ->where('parent_invoice_id', $proforma->id)
        ->firstOrFail();

    $response = $this->actingAs($user)
        ->get(route('panel.billing.invoices.print', $taxDoc));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('pdf');
});

it('blocks a customer from downloading another customer\'s invoice PDF', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();

    ['invoice' => $invoice] = placeOrder($owner);

    $this->actingAs($intruder)
        ->get(route('panel.billing.invoices.print', $invoice))
        ->assertForbidden();
});

it('shows the billing pages without error', function (string $routeName): void {
    $user = customerUser();

    $this->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'panel.billing.invoices',
    'panel.billing.payments',
    'panel.billing.credits',
]);

it('shows the invoice detail page', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)
        ->get(route('panel.billing.invoices.show', $invoice))
        ->assertOk()
        ->assertSee($invoice->number);
});
