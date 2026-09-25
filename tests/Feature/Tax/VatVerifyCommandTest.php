<?php

declare(strict_types=1);

use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Tax\Models\VatValidation;

/*
 * TASK-0031 WP A (D31.3c, D31.3d): existing customers are changed only by the operator's command or by the switch. The
 * default run of onhost:vat:verify is a dry run that makes no HTTP call and writes nothing — it lists the organizations
 * whose status would change and, for the accountant, the documents already issued with VAT to EU business customers who
 * had given a VAT ID. `--apply` checks them. The monthly re-check of the valid ones (tax.vies_recheck) is off by default.
 */

const VAT_VERIFY_ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class]);
    config(['onhost.vies.endpoint' => VAT_VERIFY_ENDPOINT, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
});

/** An EU business customer with a VAT ID that was never checked (the switch was off when it registered). */
function vatVerifyOrganization(string $slug = 'acme-gmbh', string $country = 'DE', string $vatId = 'DE123456789', array $extra = []): Organization
{
    return Organization::query()->create(array_merge(['slug' => $slug, 'name' => 'ACME '.$slug, 'owner_user_id' => 'usr_'.$slug, 'country' => $country, 'vat_id' => $vatId, 'customer_class' => 'b2b', 'currency' => 'EUR'], $extra));
}

function vatVerifyInvoice(Organization $org, string $number = 'FV-2026-0001'): Invoice
{
    $invoice = Invoice::query()->create([
        'number' => $number, 'legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'organization_id' => $org->id, 'currency' => 'EUR', 'state' => 'PAID',
        'subtotal_minor' => 10000, 'tax_minor' => 1900, 'total_minor' => 11900, 'paid_minor' => 11900, 'issued_at' => '2026-03-01 10:00:00',
        'buyer' => ['name' => $org->name, 'vat_id' => $org->vat_id, 'dic' => null, 'country' => $org->country, 'customer_class' => 'b2b', 'organization_id' => $org->id],
    ]);
    InvoiceLine::query()->create(['invoice_id' => $invoice->id, 'position' => 1, 'description' => 'Web hosting', 'unit_net_minor' => 10000, 'net_minor' => 10000, 'tax_rate' => 19, 'tax_category' => 'S', 'tax_minor' => 1900, 'total_minor' => 11900]);

    return $invoice;
}

it('lists the organizations and the past VAT invoices in a dry run, calls nobody and writes nothing', function () {
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => true]);
    $org = vatVerifyOrganization();
    $legacy = vatVerifyOrganization('legacy-partner', 'CZ', 'CZ27076551', ['vat_status' => 'payer']);
    $czech = vatVerifyOrganization('domestic', 'CZ', 'CZ87654321');
    $invoice = vatVerifyInvoice($org);
    $invoiceBefore = (array) DB::table('invoices')->where('id', $invoice->id)->first();
    $orgBefore = (array) DB::table('organizations')->where('id', $org->id)->first();

    expect(Artisan::call('onhost:vat:verify'))->toBe(0);
    $out = Artisan::output();

    expect($out)->toContain($org->id)->toContain('DE123456789')->toContain('destination VAT')
        ->toContain($legacy->id)->toContain('legacy')
        ->not->toContain($czech->id)
        ->toContain('FV-2026-0001')->toContain('dry run');
    expect((array) DB::table('invoices')->where('id', $invoice->id)->first())->toBe($invoiceBefore)
        ->and((array) DB::table('organizations')->where('id', $org->id)->first())->toBe($orgBefore)
        ->and(VatValidation::query()->count())->toBe(0)->and($legacy->fresh()->vat_status)->toBe('payer');
});

it('writes the accountant\'s list of past invoices to a CSV report when asked', function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    $org = vatVerifyOrganization();
    vatVerifyInvoice($org, 'FV-2026-0002');

    expect(Artisan::call('onhost:vat:verify', ['--csv' => 'vat-past.csv']))->toBe(0);

    Storage::disk('local')->assertExists('reports/vat-past.csv');
    expect(Storage::disk('local')->get('reports/vat-past.csv'))->toContain('FV-2026-0002')->toContain('DE123456789');
});

it('checks the listed organizations with --apply and records the answers', function () {
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => true]);
    Http::fake([VAT_VERIFY_ENDPOINT => Http::response(['countryCode' => 'DE', 'vatNumber' => '123456789', 'valid' => true, 'requestIdentifier' => 'WAPIVERIFY1', 'name' => 'ACME GmbH', 'address' => 'Berlin'])]);
    $org = vatVerifyOrganization();

    expect(Artisan::call('onhost:vat:verify', ['--apply' => true, '--pause-ms' => 0]))->toBe(0);

    expect($org->fresh()->vat_status)->toBe('valid')->and($org->fresh()->vat_consultation_number)->toBe('WAPIVERIFY1')
        ->and(VatValidation::query()->where('organization_id', $org->id)->value('reason'))->toBe('operator');
    expect(Artisan::output())->toContain('valid 1');
});

it('refuses --apply while the VIES switch is off', function () {
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false]);
    $org = vatVerifyOrganization();

    expect(Artisan::call('onhost:vat:verify', ['--apply' => true]))->toBe(1);
    expect($org->fresh()->vat_status)->toBe('unknown');
});

it('re-checks nobody while tax.vies_recheck is off, and only the stale valid ones once it is on', function () {
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => true]);
    Http::fake([VAT_VERIFY_ENDPOINT => fn ($request) => Http::response(['countryCode' => $request['countryCode'], 'vatNumber' => $request['vatNumber'], 'valid' => true, 'requestIdentifier' => 'WAPIRECHECK', 'name' => 'x', 'address' => 'y'])]);
    $stale = vatVerifyOrganization('stale', 'DE', 'DE111111111', ['vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_number' => 'DE111111111', 'vat_checked_at' => now()->subDays(26)]);
    $fresh = vatVerifyOrganization('fresh', 'DE', 'DE222222222', ['vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_number' => 'DE222222222', 'vat_checked_at' => now()->subDays(3)]);
    $never = vatVerifyOrganization('never', 'AT', 'ATU12345678');

    expect(Artisan::call('onhost:vat:recheck'))->toBe(0);
    Http::assertNothingSent();

    app(AutomationLedger::class)->setEnabled('tax.vies_recheck', true, 'test');
    expect(Artisan::call('onhost:vat:recheck', ['--pause-ms' => 0]))->toBe(0);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['vatNumber'] === '111111111');
    expect($stale->fresh()->vat_checked_at->isToday())->toBeTrue()->and($never->fresh()->vat_status)->toBe('unknown')
        ->and(app(AutomationLedger::class)->last('tax.vies_recheck')['stats'])->toMatchArray(['checked' => 1, 'valid' => 1]);
});
