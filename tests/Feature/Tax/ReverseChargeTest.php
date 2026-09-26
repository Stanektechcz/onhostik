<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoicePdfRenderer;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\UblExporter;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Tax\Commands\RecordVatCheckCommand;
use Onhost\Domain\Tax\Models\TaxCalculation;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * TASK-0031 WP B (D31.4): the tax decision uses the recorded VIES check. Reverse charge (0 %, category AE, the legend and both
 * VAT IDs on the document) only for a business of another EU member state whose number VIES confirmed at most 30 days before
 * the quote or the document; otherwise the destination VAT of today, and a review flag when a VAT ID was given. Before this,
 * the stored column was passed as the verdict by every caller and nothing ever wrote `valid`: every EU business customer was
 * charged destination VAT with no flag, and a stale or foreign check would have counted for ever once anything wrote it.
 * VIES is always faked; stray requests are refused.
 */

const VAT_RC_ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Event::fake(['onhost.order.paid']); // provisioning is not the subject
    config(['onhost.vies.enabled' => false, 'onhost.vies.endpoint' => VAT_RC_ENDPOINT, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
});

/** What VIES answered, recorded the way the platform's own check records it (system actor, through the bus). */
function vatRcRecord(Organization $organization, string $number, string $status = 'valid', ?string $consultation = 'WAPIRC0001'): Organization
{
    app(CommandBus::class)->dispatch(new RecordVatCheckCommand($organization->id, 'vat-rc:'.$organization->id.':'.uniqid('', true), [
        'number' => $number, 'status' => $status, 'consultation_number' => $consultation, 'trigger' => 'operator', 'source' => 'vies',
    ]), CommandContext::system('test'));

    return $organization->fresh();
}

/** The signed-in customer's cart quote for one web hosting. */
function vatRcCartQuote($test, User $owner, string $fqdn): array
{
    $test->actingAs($owner, 'sanctum');
    $test->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => $fqdn]]]])->assertOk();

    $data = $test->postJson('/v1/cart/quote')->assertOk()->json('data');
    // the VIES evidence and the review flag are staff-facing (review round 1): the customer's answer carries neither, the stored
    // quote (what the order, the document and finance read) carries both
    expect($data['versions'])->not->toHaveKeys(['vat', 'vat_review']);
    $data['versions'] = Quote::query()->findOrFail($data['quote_id'])->versions;

    return $data;
}

function vatRcConsents(): array
{
    return ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
}

function vatRcInvoiceHtml(Invoice $invoice): string
{
    return view('invoices.invoice', ['invoice' => $invoice, 'lines' => $invoice->lines()->get(), 'money' => fn (int $minor) => Money::minor($minor, $invoice->currency)->format('cs'), 'title' => 'Faktura'])->render();
}

it('reverse-charges a business of another member state whose number VIES confirmed, and the invoice says so with both VAT IDs', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $org = vatRcRecord($org, 'DE123456789');

    $quote = vatRcCartQuote($this, $owner, 'acme-rc.de');
    expect($quote['lines'][0]['tax_rate'])->toBe('0')->and($quote['lines'][0]['tax_category'])->toBe('AE')->and($quote['tax'])->toBe(0)
        ->and($quote['versions']['vat_review'])->toBeFalse()->and($quote['versions']['vat']['consultation_number'])->toBe('WAPIRC0001')
        ->and($quote['versions']['vat']['status'])->toBe('valid');

    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('50000', 'CZK'), 'bank', 'vat-rc-seed-1', $ctx);
    $order = app(CheckoutService::class)->placeOrder(Quote::query()->findOrFail($quote['quote_id']), $org->fresh(), $owner, vatRcConsents(), ['mode' => 'wallet'], 'vat-rc-order-1', $ctx)['order']->fresh();
    expect($order->meta['vat_review'])->toBeFalse()->and($order->meta['vat']['consultation_number'])->toBe('WAPIRC0001');

    $invoice = app(InvoiceService::class)->issueForOrder($order, $ctx, 'wallet')->fresh();
    expect($invoice->lines()->pluck('tax_category')->unique()->values()->all())->toBe(['AE'])
        ->and($invoice->buyer['vat_id'])->toBe('DE123456789')->and($invoice->buyer['vat_check']['consultation_number'])->toBe('WAPIRC0001')
        ->and($invoice->meta['vat_review'])->toBeFalse();
    $html = vatRcInvoiceHtml($invoice);
    $seller = (string) ($invoice->seller['vat_id'] ?: $invoice->seller['dic']);
    expect($html)->toContain('Daň odvede zákazník (reverse charge')->toContain('DIČ dodavatele '.$seller)->toContain('DIČ odběratele DE123456789')->toContain('WAPIRC0001')
        ->toContain('Sazba 0 % (AE)'); // the rate printed as " %" before: rtrim('0', '0') is ''
    expect(app(InvoicePdfRenderer::class)->render($invoice))->toStartWith('%PDF');
});

it('charges destination VAT and flags the order for review once the check is older than thirty days', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    vatRcRecord($org, 'DE123456789');

    $this->travelTo(now()->addDays(31));
    $quote = vatRcCartQuote($this, $owner, 'acme-stale.de');
    expect($quote['lines'][0]['tax_rate'])->toBe('19')->and($quote['lines'][0]['tax_category'])->toBe('S')->and($quote['versions']['vat_review'])->toBeTrue()
        ->and($quote['versions']['vat']['reason'])->toBe('stale');

    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('50000', 'CZK'), 'bank', 'vat-rc-seed-2', $ctx);
    $order = app(CheckoutService::class)->placeOrder(Quote::query()->findOrFail($quote['quote_id']), $org->fresh(), $owner, vatRcConsents(), ['mode' => 'wallet'], 'vat-rc-order-2', $ctx)['order']->fresh();
    expect($order->meta['vat_review'])->toBeTrue();
    $invoice = app(InvoiceService::class)->issueForOrder($order, $ctx, 'wallet')->fresh();
    expect($invoice->lines()->pluck('tax_category')->unique()->values()->all())->toBe(['S'])->and($invoice->meta['vat_review'])->toBeTrue();
});

it('keeps the money of a number never checked exactly as today and only adds the review flag', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);

    $quote = vatRcCartQuote($this, $owner, 'acme-unchecked.de');
    expect($quote['lines'][0]['tax_rate'])->toBe('19')->and($quote['lines'][0]['tax_category'])->toBe('S')->and($quote['tax'])->toBe(Money::minor($quote['subtotal'], 'CZK')->percent(19)->minor)
        ->and($quote['versions']['vat_review'])->toBeTrue();
    $calculation = TaxCalculation::query()->findOrFail(Quote::query()->findOrFail($quote['quote_id'])->tax_calculation_id);
    expect($calculation->result['vat_review'])->toBeTrue()->and($calculation->result['review_required'])->toBeTrue();
    Http::assertNothingSent();
});

it('asks VIES before the quote when the number is not known yet, and asks once', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    config(['onhost.vies.enabled' => true]);
    Http::fake([VAT_RC_ENDPOINT => fn ($request) => Http::response(['countryCode' => $request['countryCode'], 'vatNumber' => $request['vatNumber'], 'requestDate' => '2026-09-25T10:00:00.000Z', 'valid' => true, 'requestIdentifier' => 'WAPIRCSYNC1', 'name' => 'ACME GmbH', 'address' => 'Berlin'])]);

    $quote = vatRcCartQuote($this, $owner, 'acme-sync.de');
    expect($quote['lines'][0]['tax_category'])->toBe('AE')->and($quote['versions']['vat']['consultation_number'])->toBe('WAPIRCSYNC1');
    Http::assertSentCount(1);

    vatRcCartQuote($this, $owner, 'acme-sync2.de');
    Http::assertSentCount(1); // a fresh answer is not asked again
});

it('never reverse-charges while VIES cannot answer, and does not ask again within the retry window', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    config(['onhost.vies.enabled' => true]);
    Http::fake([VAT_RC_ENDPOINT => Http::response(['actionSucceed' => false, 'errorWrappers' => [['error' => 'MS_UNAVAILABLE', 'message' => 'member state down']]], 500)]);

    $quote = vatRcCartQuote($this, $owner, 'acme-down.de');
    expect($quote['lines'][0]['tax_rate'])->toBe('19')->and($quote['lines'][0]['tax_category'])->toBe('S')->and($quote['versions']['vat_review'])->toBeTrue();
    Http::assertSentCount(1);

    $this->travelTo(now()->addMinutes(5));
    expect(vatRcCartQuote($this, $owner, 'acme-down2.de')['lines'][0]['tax_category'])->toBe('S');
    Http::assertSentCount(1);
});

it('keeps a Czech business on domestic VAT whatever VIES said', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'Firma s.r.o.', 'country' => 'CZ', 'ico' => '12345678', 'dic' => 'CZ12345678']);
    vatRcRecord($org, 'CZ12345678');

    $quote = vatRcCartQuote($this, $owner, 'firma-rc.cz');
    expect($quote['lines'][0]['tax_rate'])->toBe('21')->and($quote['lines'][0]['tax_category'])->toBe('S')->and($quote['versions']['vat_review'])->toBeFalse();
});

it('does not reverse-charge on a valid number of another country than the organization', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'FR12345678901']);
    vatRcRecord($org, 'FR12345678901');

    $quote = vatRcCartQuote($this, $owner, 'acme-fr.de');
    expect($quote['lines'][0]['tax_category'])->toBe('S')->and($quote['lines'][0]['tax_rate'])->toBe('19')->and($quote['versions']['vat_review'])->toBeTrue()
        ->and($quote['versions']['vat']['reason'])->toBe('vat_country_mismatch');
});

it('renews on reverse charge while the check is fresh and on destination VAT with a review flag once it is stale', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    vatRcRecord($org, 'DE123456789');
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'vat-rc-renew-seed', $this->contextFor($owner, $org));
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'desired_spec' => [], 'entitlements' => [], 'sla_class' => 'standard']);
    $subscription = Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 44900, 'state' => 'active', 'auto_renew' => true, 'current_period_start' => now()->subMonth(), 'current_period_end' => now(), 'next_renewal_at' => now()]);

    expect(app(SubscriptionService::class)->renew($subscription, $service, CommandContext::system('test')))->toBe('renewed');
    $fresh = Invoice::query()->where('meta->subscription_id', $subscription->id)->latest('created_at')->firstOrFail();
    expect($fresh->lines()->value('tax_category'))->toBe('AE')->and($fresh->tax_minor)->toBe(0)->and($fresh->meta['vat_review'])->toBeFalse();

    $this->travelTo(now()->addDays(31));
    expect(app(SubscriptionService::class)->renew($subscription->fresh(), $service, CommandContext::system('test')))->toBe('renewed');
    $stale = Invoice::query()->where('meta->subscription_id', $subscription->id)->where('id', '!=', $fresh->id)->firstOrFail();
    expect($stale->lines()->value('tax_category'))->toBe('S')->and($stale->tax_minor)->toBe(Money::minor(44900, 'CZK')->percent(19)->minor)->and($stale->meta['vat_review'])->toBeTrue();
});

it('prints a DIČ given without a VAT ID as the buyer tax number of the document and of its UBL form', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'Firma s.r.o.', 'country' => 'CZ', 'ico' => '12345678', 'dic' => 'CZ12345678']);
    $ctx = $this->contextFor($owner, $org);
    $invoice = app(InvoiceService::class)->draft($org->fresh(), 'invoice', 'CZK', [['sku' => 'x', 'description' => 'Služba', 'qty' => 1, 'unit_net' => 10000, 'net' => 10000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 2100, 'total' => 12100]], $ctx)->fresh();

    expect($invoice->buyer['vat_id'])->toBe('CZ12345678')->and($invoice->buyer['dic'])->toBe('CZ12345678')->and($invoice->meta['vat_review'])->toBeFalse()
        ->and(app(UblExporter::class)->export($invoice))->toContain('<cac:PartyTaxScheme><cbc:CompanyID>CZ12345678</cbc:CompanyID>');
});

it('shows staff the evidence behind the customer VAT treatment', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    vatRcRecord($org, 'DE123456789');
    $this->actingAs($this->staff(), 'sanctum');

    $vat = $this->getJson("/v1/staff/customers/{$org->id}")->assertOk()->json('data.vat');
    expect($vat['status'])->toBe('valid')->and($vat['reason'])->toBe('fresh')->and($vat['consultation_number'])->toBe('WAPIRC0001')->and($vat['needs_check'])->toBeFalse();
});

it('decides the staff assisted quote by the same rule', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    config(['onhost.vies.enabled' => true]);
    Http::fake([VAT_RC_ENDPOINT => fn ($request) => Http::response(['countryCode' => $request['countryCode'], 'vatNumber' => $request['vatNumber'], 'valid' => true, 'requestIdentifier' => 'WAPIRCSTAFF'])]);
    $this->actingAs($this->staff(), 'sanctum');
    $items = ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'acme-staff.de']]]];

    $quote = $this->postJson("/v1/staff/customers/{$org->id}/orders/quote", $items)->assertOk()->json('data');
    expect($quote['lines'][0]['tax_category'])->toBe('AE');
    Http::assertSentCount(1);

    config(['onhost.vies.enabled' => false]);
    $this->travelTo(now()->addDays(31));
    $stale = $this->postJson("/v1/staff/customers/{$org->id}/orders/quote", $items)->assertOk()->json('data');
    expect($stale['lines'][0]['tax_category'])->toBe('S')->and($stale['lines'][0]['tax_rate'])->toBe('19');
});

it('flags a row from before the check that still says valid, and keeps its money', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $org->forceFill(['vat_status' => 'valid', 'vat_status_source' => null])->save(); // written before TASK-0031: reverse charge today

    $quote = vatRcCartQuote($this, $owner, 'acme-legacy.de');
    expect($quote['lines'][0]['tax_category'])->toBe('AE')->and($quote['versions']['vat_review'])->toBeTrue()->and($quote['versions']['vat']['reason'])->toBe('legacy_unverified');
});

it('quotes a plan change and an order the customer did not place through the organization, never the stored column', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $org->forceFill(['vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_number' => 'DE123456789', 'vat_checked_at' => now()->subDays(40)])->save();

    // PlanChangeService, LimitRaiseService and ServiceReinstatement quote with the organization: whatever array they pass,
    // the organization's standing decides — a stale check is not reverse charge
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'acme-pc.de']]], 'CZK', ['country' => 'DE', 'customer_class' => 'b2b', 'vat_status' => 'valid'], 1, null, $org->fresh());
    expect($quote->lines[0]['tax_category'])->toBe('S')->and($quote->versions['vat_review'])->toBeTrue();
    expect(Order::query()->count())->toBe(0);
});
