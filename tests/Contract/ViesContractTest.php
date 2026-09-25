<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\Tax\VatNumber;
use Onhost\Domain\Tax\VatNumberChecks;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Providers\Contracts\VatNumberValidator;
use Onhost\Providers\Vies\ViesVatNumberValidator;

/*
 * TASK-0031 WP A: the EU VIES REST check-vat-number endpoint behind the provider contract (D31.1). Shapes as the EC swagger
 * (swagger_publicVAT.yaml) documents them: a 200 answer carries `valid`, `requestIdentifier`, `name`, `address`, sometimes a
 * `userError`; a refusal carries `actionSucceed: false` + `errorWrappers[{error, message}]`, with HTTP 400/500 or even 200.
 * Every outage code is "unknown, try again", never "invalid" — a customer whose number VIES could not look up is not told
 * their number is wrong. Stray requests are refused by tests/Pest.php for the whole Contract suite.
 */

const VAT_VIES_ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

beforeEach(function () {
    config(['onhost.vies.enabled' => true, 'onhost.vies.endpoint' => VAT_VIES_ENDPOINT, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
});

function vatViesValidator(): VatNumberValidator
{
    return app(ViesVatNumberValidator::class);
}

/** @param array<string,mixed> $body */
function vatViesAnswer(array $body, int $status = 200): void
{
    Http::fake([VAT_VIES_ENDPOINT => Http::response($body, $status)]);
}

it('answers valid with the consultation number, name and address', function () {
    vatViesAnswer(['countryCode' => 'DE', 'vatNumber' => '123456789', 'requestDate' => '2026-09-25T10:00:00.000Z', 'valid' => true, 'requestIdentifier' => 'WAPIAAAAZ1', 'name' => '  ACME GmbH ', 'address' => "Hauptstr. 1\n10115   Berlin", 'traderNameMatch' => 'NOT_PROCESSED']);

    $result = vatViesValidator()->check('DE', '123456789', 8);

    expect($result->status)->toBe('valid')->and($result->consultationNumber)->toBe('WAPIAAAAZ1')->and($result->name)->toBe('ACME GmbH')
        ->and($result->address)->toBe('Hauptstr. 1 10115 Berlin')->and($result->requestDate)->toBe('2026-09-25T10:00:00.000Z')->and($result->retryable)->toBeFalse();
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === VAT_VIES_ENDPOINT
        && $request['countryCode'] === 'DE' && $request['vatNumber'] === '123456789' && $request['requesterMemberStateCode'] === 'CZ' && $request['requesterNumber'] === '12345678');
});

it('reads a name the member state does not disclose as no name', function () {
    vatViesAnswer(['countryCode' => 'DE', 'vatNumber' => '123456789', 'valid' => true, 'requestIdentifier' => 'WAPIAAAAZ2', 'name' => '---', 'address' => '---']);

    $result = vatViesValidator()->check('DE', '123456789', 8);

    expect($result->status)->toBe('valid')->and($result->name)->toBeNull()->and($result->address)->toBeNull();
});

it('answers invalid', function () {
    vatViesAnswer(['countryCode' => 'DE', 'vatNumber' => '123456789', 'valid' => false, 'userError' => 'INVALID', 'requestIdentifier' => '', 'name' => '---', 'address' => '---']);

    $result = vatViesValidator()->check('DE', '123456789', 8);

    expect($result->status)->toBe('invalid')->and($result->retryable)->toBeFalse()->and($result->consultationNumber)->toBeNull();
});

it('reads a malformed number VIES refused (INVALID_INPUT) as invalid', function () {
    vatViesAnswer(['actionSucceed' => false, 'errorWrappers' => [['error' => 'INVALID_INPUT', 'message' => 'The provided CountryCode is invalid or the VAT number is empty']]], 400);

    $result = vatViesValidator()->check('DE', '12345678X', 8);

    expect($result->status)->toBe('invalid')->and($result->errorCode)->toBe('invalid_input')->and($result->retryable)->toBeFalse();
});

it('answers unknown and retryable when VIES or the member state cannot answer', function (string $code, string $shape) {
    $shape === 'userError'
        ? vatViesAnswer(['countryCode' => 'DE', 'vatNumber' => '123456789', 'valid' => false, 'userError' => $code])
        : vatViesAnswer(['actionSucceed' => false, 'errorWrappers' => [['error' => $code, 'message' => 'try later']]], 500);

    $result = vatViesValidator()->check('DE', '123456789', 8);

    expect($result->status)->toBe('unknown')->and($result->retryable)->toBeTrue()->and($result->errorCode)->toBe($code);
})->with(['MS_UNAVAILABLE', 'TIMEOUT', 'SERVICE_UNAVAILABLE', 'GLOBAL_MAX_CONCURRENT_REQ', 'MS_MAX_CONCURRENT_REQ'])->with(['userError', 'errorWrappers']);

it('answers unknown without retrying when our own requester details or our address are refused', function (string $code) {
    vatViesAnswer(['actionSucceed' => false, 'errorWrappers' => [['error' => $code, 'message' => 'refused']]], 400);

    $result = vatViesValidator()->check('DE', '123456789', 8);

    expect($result->status)->toBe('unknown')->and($result->retryable)->toBeFalse()->and($result->errorCode)->toBe($code);
})->with(['INVALID_REQUESTER_INFO', 'VAT_BLOCKED', 'IP_BLOCKED']);

it('answers unknown and retryable when the connection fails', function () {
    Http::fake([VAT_VIES_ENDPOINT => fn () => throw new ConnectionException('cURL error 28: timed out')]);

    $result = vatViesValidator()->check('DE', '123456789', 8);

    expect($result->status)->toBe('unknown')->and($result->retryable)->toBeTrue();
});

it('sends EL for a Greek number', function () {
    vatViesAnswer(['countryCode' => 'EL', 'vatNumber' => '123456789', 'valid' => true, 'requestIdentifier' => 'WAPIAAAAZ3', 'name' => 'Α.Ε.', 'address' => 'Αθήνα']);

    expect(VatNumber::of('gr 123 456 789')?->value)->toBe('EL123456789')->and(VatNumber::of('EL123456789')?->toIsoCountry())->toBe('GR');
    vatViesValidator()->check('GR', '123456789', 8);

    Http::assertSent(fn (Request $request) => $request['countryCode'] === 'EL' && $request['vatNumber'] === '123456789');
});

it('never calls VIES for a malformed number', function () {
    Http::fake();
    expect(VatNumber::of('DE12')?->isWellFormed())->toBeFalse()
        ->and(VatNumber::of('DE123456789')?->isWellFormed())->toBeTrue()
        ->and(VatNumber::of('CZ1234567')?->isWellFormed())->toBeFalse()
        ->and(VatNumber::of('CZ12345678')?->isWellFormed())->toBeTrue();
    $org = Organization::query()->create(['slug' => 'vat-malformed', 'name' => 'Malformed GmbH', 'owner_user_id' => 'usr_x', 'country' => 'DE', 'vat_id' => 'DE12', 'customer_class' => 'b2b']);

    expect(app(VatNumberChecks::class)->check($org, 'operator', 8))->toBe('invalid');

    Http::assertNothingSent();
    expect(VatValidation::query()->where('organization_id', $org->id)->value('source'))->toBe('format')->and($org->fresh()->vat_status)->toBe('invalid');
});

it('writes a provider_calls row without the trader name', function () {
    vatViesAnswer(['countryCode' => 'DE', 'vatNumber' => '123456789', 'valid' => true, 'requestIdentifier' => 'WAPIAAAAZ4', 'name' => 'ACME GmbH', 'address' => 'Hauptstr. 1, Berlin']);

    vatViesValidator()->check('DE', '123456789', 8);

    $row = DB::table('provider_calls')->where('provider', 'vies')->first();
    expect($row)->not->toBeNull()->and($row->action)->toBe('check-vat-number')->and((string) $row->response)->not->toContain('ACME')->not->toContain('Hauptstr');
});

it('an open breaker answers unknown without a request', function () {
    Http::fake();
    $breaker = app(ProviderHttpClient::class)->breaker('vies');
    foreach (range(1, 6) as $i) {
        $breaker->recordFailure();
    }

    $result = vatViesValidator()->check('DE', '123456789', 8);

    expect($result->status)->toBe('unknown')->and($result->retryable)->toBeTrue()->and($result->errorCode)->toBe('CIRCUIT_OPEN');
    Http::assertNothingSent();
});

it('answers unknown with the switch off, and the contract resolves to the switch', function () {
    Http::fake();
    config(['onhost.vies.enabled' => false]);
    $result = app(VatNumberValidator::class)->check('DE', '123456789', 8);

    expect($result->status)->toBe('unknown')->and($result->errorCode)->toBe('vies_disabled')->and($result->retryable)->toBeFalse();
    config(['onhost.vies.enabled' => true]);
    expect(app(VatNumberValidator::class))->toBeInstanceOf(ViesVatNumberValidator::class);
    Http::assertNothingSent();
});
