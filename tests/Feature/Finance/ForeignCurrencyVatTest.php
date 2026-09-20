<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\UblExporter;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\CnbRates;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;

/*
 * A tax document in another currency states its VAT in CZK, at the rate of the Czech National Bank valid for the day the tax
 * is due (§ 29 and § 4 of the Czech VAT act). A document in EUR carried neither the rate nor the amount.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    config(['onhost.billing.fx.fetch' => true]);
    Http::preventStrayRequests();
});

/** The bank's list as it is published: the date it is valid for, a header row, rows with a decimal comma. */
function cnbList(string $date, string $eur, string $extra = ''): string
{
    return "{$date} #182\nzemě|měna|množství|kód|kurz\nAustrálie|dolar|1|AUD|13,861\nEMU|euro|1|EUR|{$eur}\nMaďarsko|forint|100|HUF|6,209\n{$extra}";
}

/** One handler with a switch (fakes stack): what the bank answers right now. `null` = it does not answer. */
function cnbFake(?string &$body): void
{
    Http::fake(['www.cnb.cz/*' => function (Request $r) use (&$body) {
        return $body === null ? Http::response('Service Unavailable', 503) : Http::response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }]);
}

/** A tax invoice of 100,00 + 21 % and 50,00 + 12 % in the given currency. */
function fxInvoice(Organization $org, CommandContext $ctx, string $currency = 'EUR'): Invoice
{
    $service = app(InvoiceService::class);

    return $service->issue($service->draft($org, 'invoice', $currency, [
        ['sku' => 'vps', 'description' => 'VPS Compute 4', 'qty' => 1, 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 2100, 'total' => 12100],
        ['sku' => 'book', 'description' => 'Příručka', 'qty' => 1, 'unit_net' => 5000, 'discount' => 0, 'net' => 5000, 'tax_rate' => '12', 'tax_category' => 'S', 'tax' => 600, 'total' => 5600],
    ], $ctx, null, ['postpaid' => true, 'payment_method' => 'bank']), $ctx);
}

it('reads the list of the national bank: the day it is valid for, the units a rate is for, the decimal comma', function () {
    $list = CnbRates::parse(cnbList('18.09.2026', '24,335', "USA|dolar|1|USD|20,7\nnesmysl|bez|kurzu\n"));

    expect($list['valid_on'])->toBe('2026-09-18')
        ->and($list['rates']['EUR'])->toBe(['amount' => 1, 'rate_micro' => 24_335_000])
        ->and($list['rates']['HUF'])->toBe(['amount' => 100, 'rate_micro' => 6_209_000])
        ->and($list['rates']['USD']['rate_micro'])->toBe(20_700_000)
        ->and(array_keys($list['rates']))->toBe(['AUD', 'EUR', 'HUF', 'USD']);
    expect(fn () => CnbRates::parse('<html>údržba</html>'))->toThrow(UnexpectedValueException::class);

    // integers only, rounded half away from zero: 21,00 EUR × 24,335 = 511,035 → 511,04 Kč; a credit note is the mirror image
    expect(ExchangeRate::convert(2100, 24_335_000, 1))->toBe(51104)->and(ExchangeRate::convert(-2100, 24_335_000, 1))->toBe(-51104)
        ->and(ExchangeRate::convert(10000, 6_209_000, 100))->toBe(621)->and(ExchangeRate::decimalOf(25_000_000))->toBe('25.000')->and(ExchangeRate::decimalOf(24_335_000))->toBe('24.335');
});

it('states the VAT of a document in another currency in CZK, at the rate of the day of supply — on the PDF and in the e-invoice', function () {
    $body = cnbList(now()->setTimezone('Europe/Prague')->format('d.m.Y'), '24,335');
    cnbFake($body);
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'currency' => 'EUR']);
    $ctx = $this->contextFor($owner, $org);

    $invoice = fxInvoice($org, $ctx);

    // it used to carry nothing: no rate, no amount in CZK
    $czk = $invoice->meta['czk'] ?? null;
    expect($czk)->not->toBeNull()->and($invoice->meta)->not->toHaveKey('czk_pending');
    expect($czk)->toMatchArray(['source' => 'cnb', 'basis' => 'supply_date', 'currency' => 'EUR', 'amount' => 1, 'rate' => '24.335', 'valid_on' => now()->setTimezone('Europe/Prague')->format('Y-m-d')]);
    // every VAT rate on its own, and the totals are their sums: 100 € → 2 433,50 Kč + 511,04 Kč; 50 € → 1 216,75 Kč + 146,01 Kč
    expect($czk['summary'])->toBe([
        ['rate' => '21', 'category' => 'S', 'net_minor' => 243350, 'tax_minor' => 51104],
        ['rate' => '12', 'category' => 'S', 'net_minor' => 121675, 'tax_minor' => 14601],
    ])->and($czk['tax_minor'])->toBe(65705)->and($czk['net_minor'])->toBe(365025)->and($czk['total_minor'])->toBe(430730);

    $html = view('invoices.invoice', ['invoice' => $invoice, 'lines' => $invoice->lines()->get(), 'money' => fn (int $m) => (string) $m, 'title' => 'Faktura'])->render();
    expect($html)->toContain('Kurz ČNB ke dni plnění')->toContain('1 EUR = 24,335 CZK')->toContain('daň 511,04 Kč')->toContain('657,05 Kč');
    $xml = app(UblExporter::class)->export($invoice);
    expect($xml)->toContain('<cbc:TaxCurrencyCode>CZK</cbc:TaxCurrencyCode>')->toContain('<cbc:TaxAmount currencyID="CZK">657.05</cbc:TaxAmount>');

    // a document in CZK needs none of it, and neither does a proforma (a request to pay is not a tax document)
    [$czOwner, $czOrg] = $this->customerWithOrganization([], ['country' => 'CZ']);
    $plain = fxInvoice($czOrg, $this->contextFor($czOwner, $czOrg), 'CZK');
    expect($plain->meta)->not->toHaveKey('czk')->not->toHaveKey('czk_pending');
    expect(app(UblExporter::class)->export($plain))->not->toContain('TaxCurrencyCode');
    $service = app(InvoiceService::class);
    $proforma = $service->issue($service->draft($org, 'proforma', 'EUR', [['sku' => 'vps', 'description' => 'VPS', 'qty' => 1, 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 2100, 'total' => 12100]], $ctx), $ctx);
    expect($proforma->meta)->not->toHaveKey('czk')->not->toHaveKey('czk_pending');
});

it('corrects a document at the rate of the original supply, not at the rate of the day the credit note is written', function () {
    $body = cnbList(now()->setTimezone('Europe/Prague')->format('d.m.Y'), '24,335');
    cnbFake($body);
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'currency' => 'EUR']);
    $ctx = $this->contextFor($owner, $org);
    $invoice = fxInvoice($org, $ctx);

    // weeks later the rate is another one
    $this->travel(20)->days();
    Cache::flush();
    $body = cnbList(now()->setTimezone('Europe/Prague')->format('d.m.Y'), '25,100');
    $note = app(InvoiceService::class)->creditNote($invoice, 'služba nebyla dodána', $ctx);

    expect($note->meta['czk'])->toMatchArray(['basis' => 'original', 'rate' => '24.335', 'valid_on' => $invoice->meta['czk']['valid_on']]);
    expect($note->meta['czk']['tax_minor'])->toBe(-65705)->and($note->meta['czk']['total_minor'])->toBe(-430730);
    // while a new document of that day takes the day's rate
    expect(fxInvoice($org, $ctx)->meta['czk']['rate'])->toBe('25.100');
});

it('issues the document when the bank does not answer, and completes it when the rate is known', function () {
    $body = null;
    cnbFake($body);
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'currency' => 'EUR']);
    $ctx = $this->contextFor($owner, $org);

    $invoice = fxInvoice($org, $ctx);

    // the bank is down: the customer still gets the document — it waits for its recap
    expect($invoice->state)->toBe(Invoice::ISSUED)->and($invoice->number)->not->toBeNull()->and($invoice->meta['czk_pending'] ?? null)->toBeTrue()->and($invoice->meta)->not->toHaveKey('czk');
    $hash = $invoice->pdf_hash;
    Artisan::call('onhost:fx:sync');
    expect(Artisan::output())->toContain('could not be read')->toContain('still waiting: 1');

    // a list that is more than a week old is not a rate of the day
    ExchangeRate::query()->create(['source' => 'cnb', 'currency' => 'EUR', 'valid_on' => now()->subDays(12)->format('Y-m-d'), 'amount' => 1, 'rate_micro' => 23_000_000, 'fetched_at' => now()]);
    expect(app(CnbRates::class)->rateFor('EUR', now()))->toBeNull();

    $this->travel(26)->hours();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $checks = collect(json_decode(trim(Artisan::output()), true)['checks']);
    expect($checks->firstWhere('check', 'every tax document in another currency states its VAT in CZK')['detail'])->toContain($invoice->number);

    // the bank answers again: the recap is added at the rate valid for the day of supply, the amounts of the document stay
    $body = cnbList($invoice->supply_date->format('d.m.Y'), '24,335');
    Cache::flush();
    Artisan::call('onhost:fx:sync', ['--date' => $invoice->supply_date->format('Y-m-d')]);
    expect(Artisan::output())->toContain('documents completed with their VAT in CZK: 1');
    $invoice->refresh();
    expect($invoice->meta)->not->toHaveKey('czk_pending')->and($invoice->meta['czk']['rate'])->toBe('24.335')->and($invoice->meta['czk']['tax_minor'])->toBe(65705)
        ->and($invoice->total_minor)->toBe(17700)->and($invoice->pdf_hash)->not->toBe($hash)->and($invoice->structured['TaxCurrencyCode'])->toBe('CZK');
    expect(AuditEvent::query()->where('action', 'invoice.czk_statement.completed')->where('resource_id', $invoice->id)->exists())->toBeTrue();

    // a list is stored once, however often it is fetched
    Artisan::call('onhost:fx:sync', ['--date' => $invoice->supply_date->format('Y-m-d')]);
    expect(Artisan::output())->toContain('0 new rate(s)')->toContain('documents completed with their VAT in CZK: 0');
    expect(ExchangeRate::query()->where('currency', 'EUR')->whereDate('valid_on', $invoice->supply_date->format('Y-m-d'))->count())->toBe(1);
});

it('asks the bank at most once in a quarter of an hour, and never where asking is turned off', function () {
    $body = null;
    cnbFake($body);
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'currency' => 'EUR']);
    $ctx = $this->contextFor($owner, $org);

    fxInvoice($org, $ctx);
    fxInvoice($org, $ctx);
    expect(collect(Http::recorded())->count())->toBe(1);

    config(['onhost.billing.fx.fetch' => false]);
    Cache::flush();
    fxInvoice($org, $ctx);
    expect(collect(Http::recorded())->count())->toBe(1);
});
