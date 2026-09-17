<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Pbs\PbsBackupProvider;

/*
 * The transport every adapter shares (Brain card H318): a panel that answers with more than the ceiling is cut off in
 * one controlled error — the worker never holds the whole body — while an ordinary answer passes untouched.
 */

function httpClientPbs(): PbsBackupProvider
{
    $_ENV['PBS_SIZE_TOKEN_ID'] = 'onhost@pbs!cp';
    $_ENV['PBS_SIZE_TOKEN_SECRET'] = 'secret';
    $instance = ProviderInstance::query()->create(['key' => 'pbs-size', 'provider' => 'pbs', 'name' => 'PBS', 'base_url' => 'https://pbs-size.mgmt.test:8007', 'secret_ref' => 'env://PBS_SIZE', 'state' => 'active', 'options' => ['verify_tls' => false]]);
    $registry = app(ProviderRegistry::class);
    $registry->register('pbs', PbsBackupProvider::class);
    $pbs = $registry->forInstance($instance);
    assert($pbs instanceof PbsBackupProvider);

    return $pbs;
}

it('refuses a provider response larger than the ceiling before it can exhaust the worker, and passes a normal one', function () {
    config(['onhost.provisioning.provider_max_body_bytes' => 65536]);
    Http::fake([
        'pbs-size.mgmt.test:8007/api2/json/version' => Http::sequence()
            ->push(['data' => ['version' => '3.2', 'release' => '3.2-1']])
            ->push(str_repeat('{"data":"x"}', 20000)), // ~240 kB of garbage from a broken proxy
    ]);
    $pbs = httpClientPbs();
    $first = $pbs->health();
    expect($first->healthy)->toBeTrue()->and($first->version)->toBe('3.2');

    $health = $pbs->health();
    expect($health->healthy)->toBeFalse()->and((string) $health->error)->toContain('exceeds 65536 bytes');
    expect(DB::table('provider_calls')->where('instance_key', 'pbs-size')->where('body_code', 'response_too_large')->exists())->toBeTrue();
});

it('maps an oversized body to a provider bug rather than a transport failure', function () {
    config(['onhost.provisioning.provider_max_body_bytes' => 65536]);
    Http::fake(['pbs-size.mgmt.test:8007/*' => Http::response(str_repeat('a', 70000))]);
    $pbs = httpClientPbs();
    try {
        $pbs->datastoreStatus('pbs-cz1');
        $this->fail('expected the oversized body to be refused');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::PROVIDER_BUG)->and($e->getMessage())->toContain('exceeds');
    }
});
