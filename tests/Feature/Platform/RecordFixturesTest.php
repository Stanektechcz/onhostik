<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Fixture recorder (audit §5i-6): one small sandbox payment per gateway, its creation and status payloads stored
 * redacted as contract fixtures — the same redaction as the provider call log, so no secret can land in the repo.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'gw-secret-test';
    config()->set('onhost.payments.comgate.merchant', '123456');
});

afterEach(function () {
    unset($_ENV['COMGATE_MERCHANT'], $_ENV['COMGATE_SECRET']);
});

it('records the create and status payloads of a sandbox payment, redacted, into the fixture directory', function () {
    Http::fake(function (Request $request) {
        if (! str_contains($request->url(), 'comgate.cz')) {
            return null;
        }
        if (str_ends_with($request->url(), '/payment')) {
            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'FIX-1', 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=FIX-1']);
        }

        return Http::response(['code' => 0, 'message' => 'OK', 'status' => 'PENDING', 'price' => 10000, 'curr' => 'CZK', 'secret' => 'should-never-be-stored', 'method' => 'CARD_CZ_CSOB_2']);
    });
    $out = sys_get_temp_dir().'/onhost-fixtures-'.uniqid();
    $this->artisan('onhost:fixtures:record', ['gateway' => 'comgate', '--out' => $out])->assertSuccessful()->expectsOutputToContain('provider id FIX-1');
    $create = json_decode((string) file_get_contents("{$out}/comgate/recorded_create.json"), true, 512, JSON_THROW_ON_ERROR);
    $status = json_decode((string) file_get_contents("{$out}/comgate/recorded_status.json"), true, 512, JSON_THROW_ON_ERROR);
    expect($create['payload']['transId'])->toBe('FIX-1')->and($create)->toHaveKey('recorded_at')->and($status['payload']['status'])->toBe('PENDING')->and($status['payload']['secret'])->toBe('[redacted]');
    expect((string) file_get_contents("{$out}/comgate/recorded_status.json"))->not->toContain('gw-secret-test')->not->toContain('should-never-be-stored');
    $this->artisan('onhost:fixtures:record', ['gateway' => 'nope', '--out' => $out])->assertFailed();
});
