<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\IssueTaxDocumentAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Exceptions\IncompleteBillingDetailsException;
use App\Domains\Billing\Models\Invoice;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
    Queue::fake();
});

it('auto-issues a tax document after proforma is paid', function (): void {
    $user = customerUser([
        'company_name'  => 'Test s.r.o.',
        'country_code'  => 'CZ',
    ]);

    // give the customer a billing address so tax doc can be issued
    $user->customer->addresses()->create([
        'type'         => 'billing',
        'street'       => 'Testovací 1',
        'city'         => 'Praha',
        'zip'          => '11000',
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    ['invoice' => $proforma] = placeOrder($user);

    app(ProcessMockPaymentAction::class)->execute($proforma);

    // HandleInvoicePaid should have auto-issued a tax document
    $taxDoc = Invoice::query()
        ->where('type', InvoiceType::Invoice->value)
        ->where('parent_invoice_id', $proforma->id)
        ->first();

    expect($taxDoc)->not->toBeNull()
        ->and($taxDoc->status)->toBe(InvoiceStatus::Paid)
        ->and($taxDoc->parent_invoice_id)->toBe($proforma->id)
        ->and($taxDoc->number)->toStartWith('CZ-')
        ->and($taxDoc->snapshot_name)->not->toBeNull()
        ->and($taxDoc->snapshot_street)->toBe('Testovací 1');

    expect(Activity::where('log_name', 'invoice')->where('description', 'invoice.tax_document_issued')->exists())->toBeTrue();
});

it('gracefully skips tax document when billing details are incomplete', function (): void {
    // customer has NO address — incomplete billing details
    $user = customerUser();

    ['invoice' => $proforma] = placeOrder($user);

    // Should NOT throw — HandleInvoicePaid catches IncompleteBillingDetailsException
    expect(fn () => app(ProcessMockPaymentAction::class)->execute($proforma))->not->toThrow(\Throwable::class);

    // No tax document was issued
    expect(Invoice::query()->where('type', InvoiceType::Invoice->value)->count())->toBe(0);
});

it('never issues the same tax document twice (idempotency)', function (): void {
    $user = customerUser([
        'company_name' => 'Idempotent s.r.o.',
        'country_code' => 'CZ',
    ]);

    $user->customer->addresses()->create([
        'type'         => 'billing',
        'street'       => 'Opakovací 5',
        'city'         => 'Brno',
        'zip'          => '60200',
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    ['invoice' => $proforma] = placeOrder($user);

    app(ProcessMockPaymentAction::class)->execute($proforma);

    $action = app(IssueTaxDocumentAction::class);

    // Calling issueTaxDocument again on the same paid proforma is a no-op
    $taxDoc1 = $action->execute($proforma->fresh());
    $taxDoc2 = $action->execute($proforma->fresh());

    expect(Invoice::query()->where('type', InvoiceType::Invoice->value)->count())->toBe(1)
        ->and($taxDoc1->id)->toBe($taxDoc2->id);
});

it('blocks tax document when billing details are explicitly incomplete', function (): void {
    // Customer has a billing address but city and zip are empty
    $user = customerUser(['company_name' => 'Incomplete s.r.o.', 'country_code' => 'CZ']);

    $user->customer->addresses()->create([
        'type'         => 'billing',
        'street'       => 'Nějaká ulice 1',
        'city'         => '',   // intentionally blank
        'zip'          => '',   // intentionally blank
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    ['invoice' => $proforma] = placeOrder($user);

    // Pay proforma — HandleInvoicePaid silently skips the tax doc (incomplete details)
    app(ProcessMockPaymentAction::class)->execute($proforma);

    $proforma->refresh();

    // Directly calling the action on the paid proforma now should throw
    expect(fn () => app(IssueTaxDocumentAction::class)->execute($proforma))
        ->toThrow(IncompleteBillingDetailsException::class);
});

it('admin can manually trigger tax document issuance via the panel', function (): void {
    $user = customerUser([
        'company_name' => 'Manual Doc s.r.o.',
        'country_code' => 'CZ',
    ]);

    $user->customer->addresses()->create([
        'type'         => 'billing',
        'street'       => 'Ruční 10',
        'city'         => 'Olomouc',
        'zip'          => '77900',
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    ['invoice' => $proforma] = placeOrder($user);

    // pay via admin mark-paid
    $this->actingAs(adminUser())
        ->post(route('admin.invoices.mark-paid', $proforma))
        ->assertRedirect();

    $proforma->refresh();

    // now manually trigger tax doc via admin endpoint
    $this->actingAs(adminUser())
        ->post(route('admin.invoices.tax-document', $proforma))
        ->assertRedirect();

    expect(Invoice::query()->where('type', InvoiceType::Invoice->value)->count())->toBeGreaterThan(0);
});
