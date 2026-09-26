<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Commands\RecordVatCheckCommand;
use Onhost\Domain\Tax\Jobs\CheckVatNumber;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\Tax\VatNumberChecks;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0031 review round 1 (security, QA): what a customer can and cannot do to a VAT standing staff decided, what a VIES
 * answer naming somebody else does, and how often VIES is asked when the same number is queued twice. VIES is always faked;
 * stray requests are refused.
 */

const VAT_R1_ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false, 'onhost.vies.endpoint' => VAT_R1_ENDPOINT, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
});

/** VIES answers valid for every number, naming the given trader. */
function vatR1FakeValid(string $name = 'ACME GmbH', string $consultation = 'WAPIR1'): void
{
    Http::fake([
        VAT_R1_ENDPOINT => fn ($request) => Http::response(['countryCode' => $request['countryCode'], 'vatNumber' => $request['vatNumber'], 'requestDate' => '2026-09-25T10:00:00.000Z', 'valid' => true, 'requestIdentifier' => $consultation, 'name' => $name, 'address' => 'Hauptstr. 1, 10115 Berlin']),
    ]);
}

/** The state OverrideVatStatusHandler leaves behind (the four-eyes flow itself is VatStatusOverrideTest's subject). */
function vatR1Override(Organization $organization, string $status, int $days = 30): Organization
{
    $organization->forceFill(['vat_status' => $status, 'vat_status_source' => 'staff', 'vat_override_until' => now()->addDays($days), 'vat_checked_number' => VatStanding::subject($organization)?->value, 'vat_checked_at' => null])->save();

    return $organization->fresh();
}

function vatR1Record(Organization $organization, string $number, string $trigger, ?string $name = null): array
{
    return (array) app(CommandBus::class)->dispatch(new RecordVatCheckCommand($organization->id, 'vat-r1:'.$organization->id.':'.uniqid('', true), [
        'number' => $number, 'status' => 'valid', 'consultation_number' => 'WAPIR1REC', 'trigger' => $trigger, 'source' => 'vies', 'name' => $name,
    ]), CommandContext::system('test'));
}

function vatR1Quote(Organization $organization): array
{
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'r1-'.uniqid().'.de']]], 'CZK', [], 1, null, $organization);

    return ['category' => $quote->lines[0]['tax_category'], 'vat_review' => $quote->versions['vat_review'], 'reasons' => $quote->versions['tax_reasons']];
}

// ── a staff override holds against the customer (security HIGH) ────────────────────────────────────────────────────────

it('keeps a staff override against a customer who re-saves the same VAT number', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $org = vatR1Override($org, 'invalid');
    config(['onhost.vies.enabled' => true]);
    vatR1FakeValid();

    $this->actingAs($owner, 'sanctum');
    $this->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'vat-r1-resave'])->patchJson("/v1/organizations/{$org->id}", ['vat_id' => 'DE123456789'])->assertOk();

    $fresh = $org->fresh();
    expect($fresh->vat_status)->toBe('invalid')->and($fresh->vat_status_source)->toBe('staff')->and($fresh->vat_override_until)->not->toBeNull()
        ->and(VatStanding::standing($fresh))->toBe(['status' => 'invalid', 'reason' => 'staff_override']);
    Http::assertNothingSent();
    expect(vatR1Quote($fresh)['category'])->toBe('S');
});

it('asks VIES nothing under a staff override for the customer, the checkout or the monthly re-check', function (string $trigger) {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $org = vatR1Override($org, 'invalid');
    config(['onhost.vies.enabled' => true]);
    vatR1FakeValid();

    expect(app(VatNumberChecks::class)->check($org, $trigger))->toBe('skipped');
    Http::assertNothingSent();
    expect($org->fresh()->vat_status_source)->toBe('staff');
})->with(['vat_id_changed', 'checkout', 'recheck']);

it('refuses to record a verdict over a staff override unless the operator asked for it, and says whose decision it replaced', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $org = vatR1Override($org, 'invalid');

    expect(vatR1Record($org, 'DE123456789', 'vat_id_changed'))->toBe(['recorded' => false, 'reason' => 'staff_override']);
    expect($org->fresh()->vat_status_source)->toBe('staff')->and(VatValidation::query()->count())->toBe(0);

    expect(vatR1Record($org, 'DE123456789', 'operator')['recorded'])->toBeTrue();
    expect($org->fresh()->vat_status_source)->toBe('vies');
    $event = OutboxMessage::query()->where('name', 'tax.vat_number.checked')->latest('created_at')->firstOrFail();
    expect($event->payload['previous_source'])->toBe('staff');
});

it('leaves an organization under a staff override out of the operator\'s --apply', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    vatR1Override($org, 'valid');
    config(['onhost.vies.enabled' => true]);
    vatR1FakeValid();

    $this->artisan('onhost:vat:verify', ['--apply' => true, '--pause-ms' => 0])->assertSuccessful();
    Http::assertNothingSent();
    expect($org->fresh()->vat_status_source)->toBe('staff');
});

// ── a staff override counts only for the country it was set for (security LOW) ──────────────────────────────────────────

it('does not reverse-charge an override set for a German number once the organization moves to Austria', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    $org = vatR1Override($org, 'valid');
    expect(vatR1Quote($org)['category'])->toBe('AE');

    $this->actingAs($owner, 'sanctum');
    $this->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'vat-r1-move'])->patchJson("/v1/organizations/{$org->id}", ['country' => 'AT'])->assertOk();

    $moved = $org->fresh();
    expect(VatStanding::standing($moved))->toBe(['status' => 'unknown', 'reason' => 'vat_country_mismatch']);
    $quote = vatR1Quote($moved);
    expect($quote['category'])->toBe('S')->and($quote['vat_review'])->toBeTrue();
});

// ── somebody else's valid number (security MEDIUM) ──────────────────────────────────────────────────────────────────────

it('keeps a VIES verdict naming another trader but flags the quote, the order evidence and the operator list for review', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Schwindel Handels GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    vatR1Record($org, 'DE123456789', 'operator', 'SIEMENS AKTIENGESELLSCHAFT');
    $org = $org->fresh();

    expect(VatStanding::effectiveStatus($org))->toBe('valid')->and(VatStanding::snapshot($org)['name_mismatch'])->toBeTrue();
    $quote = vatR1Quote($org);
    expect($quote['category'])->toBe('AE')->and($quote['vat_review'])->toBeTrue()->and(implode(' ', $quote['reasons']))->toContain('name');
    expect(VatStanding::invoiceNeedsReview(['vat_id' => 'DE123456789', 'country' => 'DE', 'customer_class' => 'b2b', 'vat_check' => VatStanding::snapshot($org)], [['tax_category' => 'AE', 'tax' => 0]]))->toBeTrue();

    $this->artisan('onhost:vat:verify')->expectsOutputToContain('name_mismatch')->assertSuccessful();
});

it('does not flag a VIES name that is the organization\'s own, written another way, or one VIES does not disclose', function (?string $viesName) {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Müller & Söhne GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    vatR1Record($org, 'DE123456789', 'operator', $viesName);
    $org = $org->fresh();

    expect(VatStanding::snapshot($org)['name_mismatch'])->toBeFalse();
    expect(vatR1Quote($org))->toMatchArray(['category' => 'AE', 'vat_review' => false]);
})->with(['same words' => 'MUELLER UND SOEHNE GMBH', 'plain' => 'Müller & Söhne GmbH', 'without legal form' => 'Müller & Söhne', 'not disclosed' => null]);

// ── the accountant's CSV (security MEDIUM) ─────────────────────────────────────────────────────────────────────────────

it('writes a buyer name that starts like a formula into the accountant\'s CSV as text', function () {
    Storage::fake('local');
    $org = Organization::query()->create(['slug' => 'r1-formula', 'name' => '=HYPERLINK("http://evil.example","x")', 'owner_user_id' => 'usr_r1', 'country' => 'DE', 'vat_id' => 'DE123456789', 'customer_class' => 'b2b', 'currency' => 'EUR']);
    $invoice = Invoice::query()->create([
        'number' => 'FV-R1-0001', 'legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'organization_id' => $org->id, 'currency' => 'EUR', 'state' => 'PAID',
        'subtotal_minor' => 10000, 'tax_minor' => 1900, 'total_minor' => 11900, 'paid_minor' => 11900, 'issued_at' => '2026-03-01 10:00:00',
        'buyer' => ['name' => '=HYPERLINK("http://evil.example","x")', 'vat_id' => '+DE123456789', 'dic' => null, 'country' => 'DE', 'customer_class' => 'b2b', 'organization_id' => $org->id],
    ]);
    InvoiceLine::query()->create(['invoice_id' => $invoice->id, 'position' => 1, 'description' => 'Web hosting', 'unit_net_minor' => 10000, 'net_minor' => 10000, 'tax_rate' => 19, 'tax_category' => 'S', 'tax_minor' => 1900, 'total_minor' => 11900]);

    $this->artisan('onhost:vat:verify', ['--csv' => 'r1.csv'])->assertSuccessful();
    $csv = Storage::disk('local')->get('reports/r1.csv');
    expect($csv)->toContain("\"'=HYPERLINK(")->toContain(",'+DE123456789,")->not->toMatch('/(^|,)=HYPERLINK/m');
});

// ── the same number queued twice (QA MEDIUM) ───────────────────────────────────────────────────────────────────────────

it('asks VIES once when the check of the same number is queued twice in a row', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'ACME GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    config(['onhost.vies.enabled' => true]);
    vatR1FakeValid();

    CheckVatNumber::dispatchSync($org->id);
    CheckVatNumber::dispatchSync($org->id);

    Http::assertSentCount(1);
    expect(VatValidation::query()->where('organization_id', $org->id)->count())->toBe(1);
});
