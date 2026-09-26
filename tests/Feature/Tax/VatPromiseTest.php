<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Commands\RecordVatCheckCommand;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\VatNumberValidator;
use Onhost\Providers\Vies\ViesVatNumberValidator;

/*
 * TASK-0031 WP C (D31.7): the public knowledge base promises that VAT IDs are validated automatically against VIES and that
 * EU companies with a valid VAT ID are billed under reverse charge. Before TASK-0031 nothing ever asked VIES, so the promise
 * was false for every EU business customer. This test pins both halves: the article says it (including the one honest
 * qualifier — until the number is verified, e.g. while VIES is not answering, the customer's country's VAT is charged), and
 * the code keeps it once ONHOST_VIES_ENABLED is on. The article text comes from prototype-content.json; a re-export from
 * the prototype that drops the qualifier fails here. VIES is always faked; stray requests are refused.
 */

const VAT_PROMISE_ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

const VAT_PROMISE_QUALIFIER_CS = 'Dokud DIČ ve VIES ověřené není (třeba když VIES zrovna neodpovídá), účtujeme DPH vaší země.';

const VAT_PROMISE_QUALIFIER_EN = 'Until the VAT ID is verified in VIES (for example while VIES is not answering), we charge the VAT of your country.';

beforeEach(function () {
    $this->seed([ContentSeeder::class, CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Event::fake(['onhost.order.paid']); // provisioning is not the subject
    config(['onhost.vies.enabled' => false, 'onhost.vies.endpoint' => VAT_PROMISE_ENDPOINT, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
});

/** The text of the article's VAT section in one locale, as the public API serves it. */
function vatPromiseSection($test, string $locale, string $heading): string
{
    $body = $test->getJson('/v1/kb/faktury-dph'.($locale === 'en' ? '?locale=en' : ''))->assertOk()->json('data.body');
    $section = collect($body)->first(fn ($pair) => is_array($pair) && ($pair[0] ?? null) === $heading);
    expect($section)->not->toBeNull();

    return (string) $section[1];
}

/** A VIES verdict recorded the way the platform's own check records it (system actor, through the bus). */
function vatPromiseRecordValid(Organization $organization, string $number): Organization
{
    app(CommandBus::class)->dispatch(new RecordVatCheckCommand($organization->id, 'vat-promise:'.$organization->id.':'.uniqid('', true), [
        'number' => $number, 'status' => 'valid', 'consultation_number' => 'WAPIPROMISE1', 'trigger' => 'operator', 'source' => 'vies',
    ]), CommandContext::system('test'));

    return $organization->fresh();
}

/** The signed-in customer's cart quote for one web hosting. */
function vatPromiseCartQuote($test, User $owner, string $fqdn): array
{
    $test->actingAs($owner, 'sanctum');
    $test->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => $fqdn]]]])->assertOk();

    return $test->postJson('/v1/cart/quote')->assertOk()->json('data');
}

it('tells customers in both languages that VAT IDs are checked in VIES, and what they pay until a number is verified', function () {
    $cs = vatPromiseSection($this, 'cs', 'DPH');
    $en = vatPromiseSection($this, 'en', 'VAT');

    // the promise itself is kept word for word, the qualifier is added after it
    expect($cs)->toStartWith('Českým firmám i osobám účtujeme 21 %. Firmám z EU s platným DIČ účtujeme v režimu reverse charge bez DPH. DIČ ověřujeme automaticky proti systému VIES.')
        ->toContain('VIES')->toEndWith(VAT_PROMISE_QUALIFIER_CS);
    expect($en)->toStartWith('Czech companies and individuals are charged 21%. EU companies with a valid VAT ID are billed under reverse charge without VAT. We validate IDs automatically against VIES.')
        ->toContain('VIES')->toEndWith(VAT_PROMISE_QUALIFIER_EN);
});

it('keeps the promise once VIES is switched on: a verified EU company is reverse-charged, an unverified one pays its country\'s VAT and is flagged', function () {
    [$verifiedOwner, $verified] = $this->customerWithOrganization([], ['name' => 'Promise GmbH', 'country' => 'DE', 'vat_id' => 'DE123456789']);
    [$waitingOwner, $waiting] = $this->customerWithOrganization([], ['name' => 'Warten GmbH', 'country' => 'DE', 'vat_id' => 'DE987654321']);
    vatPromiseRecordValid($verified, 'DE123456789');

    config(['onhost.vies.enabled' => true]);
    expect(app(VatNumberValidator::class))->toBeInstanceOf(ViesVatNumberValidator::class);
    // VIES is not answering for the member state: the unverified number stays unknown (never called invalid)
    Http::fake([VAT_PROMISE_ENDPOINT => Http::response(['actionSucceed' => false, 'errorWrappers' => [['error' => 'MS_UNAVAILABLE', 'message' => 'member state unavailable']]], 500)]);

    $reverse = vatPromiseCartQuote($this, $verifiedOwner, 'promise-verified.de');
    expect($reverse['lines'][0]['tax_category'])->toBe('AE')->and($reverse['lines'][0]['tax_rate'])->toBe('0')->and($reverse['tax'])->toBe(0)
        ->and($reverse['versions']['vat_review'])->toBeFalse();
    Http::assertNothingSent(); // a fresh verdict is not asked again

    $destination = vatPromiseCartQuote($this, $waitingOwner, 'promise-waiting.de');
    expect($destination['lines'][0]['tax_category'])->toBe('S')->and($destination['lines'][0]['tax_rate'])->toBe('19')
        ->and($destination['versions']['vat_review'])->toBeTrue()->and($destination['versions']['vat']['status'])->toBe('unknown');
    Http::assertSentCount(1); // the quote asked VIES once for the number it did not know
    expect($waiting->fresh()->vat_status)->not->toBe('invalid');
});
