<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\SlaCredit;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Tax\Commands\OverrideVatStatusCommand;
use Onhost\Domain\Tax\Commands\OverrideVatStatusHandler;
use Onhost\Domain\Tax\Jobs\CheckVatNumber;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\Tax\VatNumberChecks;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0031 review round 2 (billing, security): a Czech partner that already exists becomes a VAT payer only through the
 * operator's command, and until then its self-billing document does not say it is not one; a staff override belongs to the
 * number, not to the state of the row a customer can reset; one organization cannot use up the VIES budget; an SLA credit
 * note takes the tax of the supply it credits; the small wording and exposure findings. VIES is always faked.
 */

const VAT_R2_ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false, 'onhost.vies.endpoint' => VAT_R2_ENDPOINT, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
});

/** VIES answers valid (or invalid) for every number, with the trader name the organization uses. */
function vatR2Fake(bool $valid = true, string $name = '---'): void
{
    Http::fake([
        VAT_R2_ENDPOINT => fn ($request) => Http::response(['countryCode' => $request['countryCode'], 'vatNumber' => $request['vatNumber'], 'requestDate' => '2026-09-26T10:00:00.000Z', 'valid' => $valid, 'requestIdentifier' => 'WAPIR2', 'name' => $name, 'address' => '---']),
    ]);
}

/** A partner organization as it exists before TASK-0031: a Czech DIČ, never checked, an approved partner row with commission. */
function vatR2ExistingCzechPartner(): Organization
{
    $org = Organization::query()->create(['slug' => 'pixel-'.uniqid(), 'name' => 'Agentura Pixel s.r.o.', 'owner_user_id' => 'usr_pixel', 'country' => 'CZ', 'dic' => 'CZ12345678', 'ico' => '12345678', 'customer_class' => 'b2b', 'currency' => 'CZK']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($org, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $org->id, 'invoice_id' => 'inv-'.uniqid(), 'period' => now()->format('Y-m'), 'kind' => 'share', 'base_minor' => 1000000, 'rate_pct' => 20, 'amount_minor' => 200000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()->subDay()]);

    return $org->fresh();
}

/** @return array<string,mixed> the self-billing snapshot of a 1 000 CZK payout */
function vatR2Payout(Organization $org): array
{
    $partners = app(PartnerService::class);
    $partner = $partners->partnerFor($org);

    return (array) $partners->requestPayout($partner, Money::minor(100000, 'CZK'), 'CZ6508000000192000145399', CommandContext::system('test')->withScope($org->id))->self_billing;
}

function vatR2Override(Organization $organization, string $status): void
{
    app(OverrideVatStatusHandler::class)->handle(new OverrideVatStatusCommand('vat-r2-override:'.uniqid(), [
        'organization_id' => $organization->id, 'status' => $status, 'reason' => 'VIES says valid, the registration was cancelled', 'evidence' => 'letter of the tax office', 'days' => 30, ...vatOverrideSubject($organization),
    ]), CommandContext::system('test'));
}

function vatR2Patch($test, $owner, Organization $org, string $vatId): void
{
    $test->actingAs($owner, 'sanctum');
    $test->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'vat-r2-'.uniqid('', true)])->patchJson("/v1/organizations/{$org->id}", ['vat_id' => $vatId])->assertOk();
}

// ── an existing Czech partner reaches the check (billing HIGH) ─────────────────────────────────────────────────────────

it('lists an existing Czech partner with an unchecked DIČ in the dry run, checks it on --apply, and pays VAT once finance confirmed it', function () {
    $partner = vatR2ExistingCzechPartner();
    $customer = Organization::query()->create(['slug' => 'domestic-r2', 'name' => 'Domestic s.r.o.', 'owner_user_id' => 'usr_dom', 'country' => 'CZ', 'dic' => 'CZ87654321', 'customer_class' => 'b2b']);
    config(['onhost.vies.enabled' => true]);
    vatR2Fake();

    expect(Artisan::call('onhost:vat:verify'))->toBe(0);
    $out = Artisan::output();
    expect($out)->toContain($partner->id)->toContain('partner')->not->toContain($customer->id);
    Http::assertNothingSent();

    expect(Artisan::call('onhost:vat:verify', ['--apply' => true, '--pause-ms' => 0]))->toBe(0);
    Http::assertSentCount(1);
    // closing review: VIES alone does not pay VAT out — the partner edits its own name — finance confirms the supplier once
    expect(VatStanding::payerStanding($partner->fresh()))->toBe(['payer' => false, 'reason' => 'identity_unconfirmed']);
    vatR2Override($partner->fresh(), 'valid');
    expect(VatStanding::isVatPayer($partner->fresh()))->toBeTrue();
    $snapshot = vatR2Payout($partner->fresh());
    expect($snapshot['tax_category'])->toBe('S')->and((float) $snapshot['tax_rate'])->toBe(21.0);
});

it('counts the Czech partners whose DIČ was never checked in the doctor', function () {
    vatR2ExistingCzechPartner();

    expect(Artisan::call('onhost:doctor', ['--json' => true]))->toBeIn([0, 1]);
    $rows = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->where('area', 'tax')->values();
    expect($rows[1]['status'])->toBe('WARN')->and($rows[1]['detail'])->toContain('1 partner');
});

it('does not tell the self-billing document that a partner whose number was never checked is not a VAT payer', function () {
    $snapshot = vatR2Payout(vatR2ExistingCzechPartner());

    expect($snapshot['tax_category'])->toBe('E')->and((float) $snapshot['tax_rate'])->toBe(0.0)
        ->and($snapshot['note_vat'])->not->toContain('není plátcem')->toContain('neověřena')
        ->and($snapshot['vat_review'])->toBeTrue();
});

it('still says a partner is not a VAT payer when the check said so, or when there is no number at all', function () {
    $checked = vatR2ExistingCzechPartner();
    config(['onhost.vies.enabled' => true]);
    vatR2Fake(valid: false);
    $this->artisan('onhost:vat:verify', ['--apply' => true, '--pause-ms' => 0])->assertSuccessful();

    $snapshot = vatR2Payout($checked->fresh());
    expect($snapshot['note_vat'])->toContain('Dodavatel není plátcem DPH')->and($snapshot['vat_review'])->toBeFalse();
});

it('checks the number of an organization that applies to be a partner, and again when it is approved', function () {
    Queue::fake();
    config(['onhost.vies.enabled' => true]);
    $org = Organization::query()->create(['slug' => 'apply-r2', 'name' => 'Apply s.r.o.', 'owner_user_id' => 'usr_apply', 'country' => 'CZ', 'dic' => 'CZ12345678', 'customer_class' => 'b2b']);
    $partners = app(PartnerService::class);

    $partner = $partners->apply($org, ['model' => 'share'], CommandContext::system('test'));
    Queue::assertPushed(CheckVatNumber::class, fn (CheckVatNumber $job) => $job->organizationId === $org->id);
    $partners->approve($partner, CommandContext::system('test'));
    Queue::assertPushed(CheckVatNumber::class, 2);
});

// ── a staff override belongs to the number (security HIGH) ────────────────────────────────────────────────────────────

it('keeps an invalid staff override when the customer changes the number and changes it back', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    vatR2Override($org, 'invalid');
    config(['onhost.vies.enabled' => true]);
    vatR2Fake();

    vatR2Patch($this, $owner, $org, 'DE999999999');
    vatR2Patch($this, $owner, $org, 'DE123456789');

    $fresh = $org->fresh();
    expect(VatStanding::standing($fresh))->toBe(['status' => 'invalid', 'reason' => 'staff_override'])
        ->and(VatValidation::query()->where('vat_id', 'DE123456789')->where('source', 'vies')->count())->toBe(0);
    Http::assertNotSent(fn ($request) => $request['vatNumber'] === '123456789');
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'r2-'.uniqid().'.de']]], 'CZK', [], 1, null, $fresh);
    expect($quote->lines[0]['tax_category'])->toBe('S');
    expect(VatStanding::snapshot($fresh)['source'])->toBe('staff')->and(VatStanding::snapshot($fresh)['override_until'])->not->toBeNull();
});

it('lets the operator\'s check replace an override that the number carries, as before', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    vatR2Override($org, 'invalid');
    config(['onhost.vies.enabled' => true]);
    vatR2Fake();
    vatR2Patch($this, $owner, $org, 'DE999999999');
    vatR2Patch($this, $owner, $org, 'DE123456789');

    expect(app(VatNumberChecks::class)->check($org->fresh(), 'operator', 8))->toBe('valid');

    // the operator's verdict is the organization's recorded check for this number now; the older override row stays evidence
    expect(VatStanding::standing($org->fresh()))->toBe(['status' => 'valid', 'reason' => 'fresh'])
        ->and(VatValidation::query()->where('vat_id', 'DE123456789')->where('source', 'staff')->count())->toBe(1);
});

// ── one organization cannot use up the VIES budget (security MEDIUM) ─────────────────────────────────────────────────

it('asks VIES at most five times an hour for one organization, however often the number changes', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'Flood GmbH', 'country' => 'DE', 'vat_id' => 'DE111111111']);
    config(['onhost.vies.enabled' => true]);
    vatR2Fake(valid: false);

    foreach (range(1, 10) as $i) {
        vatR2Patch($this, $owner, $org, $i % 2 === 0 ? 'DE111111111' : 'DE222222222');
    }

    Http::assertSentCount(5);
    expect(VatStanding::effectiveStatus($org->fresh()))->toBe('unknown');
});

// ── an SLA credit takes the tax of the supply it credits (billing MEDIUM) ─────────────────────────────────────────────

it('credits an SLA credit with the tax category and rate of the invoice line it credits, not today\'s standing', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    // VIES-valid 40 days ago: reverse-charged then, only destination VAT today
    $org->forceFill(['vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_number' => 'DE123456789', 'vat_checked_at' => now()->subDays(40)])->save();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS Business', 'hostname' => 'vps-r2.onhost.cloud', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'business', 'activated_at' => now()->subMonths(2)]);
    $invoice = Invoice::query()->create(['number' => 'FV-R2-0001', 'legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => 'PAID',
        'subtotal_minor' => 100000, 'tax_minor' => 0, 'total_minor' => 100000, 'paid_minor' => 100000, 'issued_at' => now()->subDays(35), 'buyer' => ['name' => 'ACME GmbH', 'vat_id' => 'DE123456789', 'country' => 'DE', 'customer_class' => 'b2b']]);
    InvoiceLine::query()->create(['invoice_id' => $invoice->id, 'position' => 1, 'description' => 'VPS Business', 'unit_net_minor' => 100000, 'net_minor' => 100000, 'tax_rate' => 0, 'tax_category' => 'AE', 'tax_minor' => 0, 'total_minor' => 100000,
        'service_id' => $service->id, 'period_from' => now()->subDays(35)->toDateString(), 'period_to' => now()->subDays(5)->toDateString()]);
    $incident = Incident::query()->create(['number' => 'INC-R2-0001', 'title' => 'Výpadek', 'severity' => 'p1', 'state' => 'RESOLVED', 'components' => ['cloud-cz1'], 'affected_services' => [$service->id], 'started_at' => now()->subDays(20), 'detected_at' => now()->subDays(20), 'resolved_at' => now()->subDays(20)->addHours(10)]);
    $credit = SlaCredit::query()->create(['organization_id' => $org->id, 'incident_id' => $incident->id, 'service_id' => $service->id, 'credit_percent' => 25, 'amount_minor' => 25000, 'currency' => 'CZK', 'state' => 'approved', 'calculation' => []]);
    expect(VatStanding::effectiveStatus($org->fresh()))->toBe('unknown');

    $issued = app(SlaService::class)->issue($credit, CommandContext::system('test'));

    $line = InvoiceLine::query()->where('invoice_id', $issued->invoice_id)->sole();
    expect($line->tax_category)->toBe('AE')->and((float) $line->tax_rate)->toBe(0.0)->and((int) $line->tax_minor)->toBe(0)->and((int) $line->net_minor)->toBe(-25000);
});

it('credits an SLA credit with today\'s decision when no invoice line of the service covers the incident', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Domácí s.r.o.', 'country' => 'CZ']);
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS Business', 'hostname' => 'vps-r2b.onhost.cloud', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'business', 'activated_at' => now()->subMonths(2)]);
    $incident = Incident::query()->create(['number' => 'INC-R2-0002', 'title' => 'Výpadek', 'severity' => 'p1', 'state' => 'RESOLVED', 'components' => ['cloud-cz1'], 'affected_services' => [$service->id], 'started_at' => now()->subDays(2), 'detected_at' => now()->subDays(2), 'resolved_at' => now()->subDays(2)->addHours(10)]);
    $credit = SlaCredit::query()->create(['organization_id' => $org->id, 'incident_id' => $incident->id, 'service_id' => $service->id, 'credit_percent' => 25, 'amount_minor' => 25000, 'currency' => 'CZK', 'state' => 'approved', 'calculation' => []]);

    $issued = app(SlaService::class)->issue($credit, CommandContext::system('test'));

    $line = InvoiceLine::query()->where('invoice_id', $issued->invoice_id)->sole();
    expect($line->tax_category)->toBe('S')->and((float) $line->tax_rate)->toBe(21.0)->and((int) $line->tax_minor)->toBe(-5250);
});

// ── LOW findings ──────────────────────────────────────────────────────────────────────────────────────────────────────

it('tells a Czech organization whose DIČ is not in VIES only in the portal, in plain words, without a warning mail', function () {
    config(['onhost.vies.enabled' => true]);
    vatR2Fake(valid: false);
    [, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'dic' => 'CZ12345678', 'billing_email' => 'billing@firma.cz']);
    app(OutboxPublisher::class)->relayPending();

    $note = Notification::query()->where('organization_id', $org->id)->where('event', 'tax.vat_number.checked')->where('audience', 'customer')->sole();
    expect($note->severity)->toBe('info')->and($note->body)->toContain('není v registru plátců DPH')->not->toContain('napište nám');
    expect(MailOutbox::query()->where('template_key', 'vat-number-invalid')->exists())->toBeFalse();
});

it('raises the VAT review flag only for a business customer, as the invoice predicate does', function () {
    $lines = [['key' => 'l1', 'net' => Money::minor(10000, 'EUR'), 'product_class' => 'esd']];
    $engine = app(TaxEngine::class);

    $consumer = $engine->calculate(['country' => 'DE', 'customer_class' => 'b2c', 'vat_id' => 'DE123456789', 'vat_status' => 'invalid'], $lines, 'EUR');
    $business = $engine->calculate(['country' => 'DE', 'customer_class' => 'b2b', 'vat_id' => 'DE123456789', 'vat_status' => 'invalid'], $lines, 'EUR');

    expect($consumer['vat_review'])->toBeFalse()->and($business['vat_review'])->toBeTrue();
});

it('shows the customer only the VIES evidence the document prints, and staff the whole check', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $invoice = Invoice::query()->create(['number' => 'FV-R2-0100', 'legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'organization_id' => $org->id, 'currency' => 'EUR', 'state' => 'ISSUED',
        'subtotal_minor' => 10000, 'tax_minor' => 0, 'total_minor' => 10000, 'paid_minor' => 0, 'issued_at' => now(),
        'buyer' => ['name' => 'ACME GmbH', 'vat_id' => 'DE123456789', 'country' => 'DE', 'customer_class' => 'b2b', 'vat_check' => ['number' => 'DE123456789', 'status' => 'valid', 'reason' => 'fresh', 'stored_status' => 'valid', 'source' => 'vies',
            'checked_at' => '2026-09-20T10:00:00+00:00', 'consultation_number' => 'WAPIR2', 'override_until' => null, 'name_mismatch' => true]]]);

    $this->actingAs($owner, 'sanctum');
    $buyer = $this->withHeaders(['X-Organization' => $org->id])->getJson("/v1/invoices/{$invoice->id}")->assertOk()->json('data.buyer');

    expect($buyer['vat_check'])->toBe(['checked_at' => '2026-09-20T10:00:00+00:00', 'consultation_number' => 'WAPIR2', 'source' => 'vies'])
        ->and($buyer)->toHaveKey('vat_id', 'DE123456789');

    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');
    $staffInvoice = collect($this->getJson("/v1/staff/customers/{$org->id}")->assertOk()->json('data.invoices'))->firstWhere('id', $invoice->id);
    expect($staffInvoice['buyer']['vat_check']['name_mismatch'])->toBeTrue()->and($staffInvoice['buyer']['vat_check']['reason'])->toBe('fresh');
});
