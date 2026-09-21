<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\IspConfig\IspConfigWebProvider;

/*
 * ISPConfig applies nothing in the response: the server cron reads `sys_datalog` and writes the outcome back into that
 * row. The adapter watched the length of the WHOLE server's queue (`monitor_jobqueue_count`, audit §2), which gives
 * the wrong answer twice over — an empty queue was reported as success even when our own job had failed, and on a
 * busy server somebody else's writes kept ours waiting until the timeout, though it had long been applied.
 *
 * Now a write the connector can name carries its change-log row in the handle and that row decides. It is opt-in on
 * evidence: only a panel whose nightly probe really returned a `status` field is followed this way.
 */

/** The adapter for a panel whose change log the nightly probe has already read. @param list<string> $fields */
function datalogAdapter(array $fields = ['datalog_id', 'dbtable', 'dbidx', 'status', 'error']): IspConfigWebProvider
{
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $instance = ProviderInstance::query()->updateOrCreate(['key' => 'ispconfig-shared01'], [
        'provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active',
        'options' => ['server_id' => 1, 'verify_tls' => false],
        'capabilities' => ['prereqs' => ['probes' => ['datalog_fields' => $fields]]],
    ]);
    $registry = app(ProviderRegistry::class);
    $registry->register('ispconfig', IspConfigWebProvider::class);

    return $registry->forInstance($instance->fresh());
}

/** @param array<string,mixed> $datalog what `sys_datalog_get_by_tstamp` answers */
function datalogFake(array $datalog, int $queue = 0, array &$asked = []): void
{
    $base = 'shared01.mgmt.test:8080/remote/json.php?';
    Http::fake([
        $base.'login' => Http::response(['code' => 'ok', 'message' => '', 'response' => 'sess-dl']),
        $base.'sites_web_domain_get' => Http::response(['code' => 'ok', 'message' => '', 'response' => ['domain_id' => 7, 'domain' => 'shop.cz', 'server_id' => 1, 'php' => 'php-fpm', 'apache_directives' => '', 'nginx_directives' => '']]),
        $base.'sites_web_domain_update' => Http::response(['code' => 'ok', 'message' => '', 'response' => true]),
        $base.'server_get_php_versions' => Http::response(['code' => 'ok', 'message' => '', 'response' => ['8.3']]),
        $base.'sys_datalog_get_by_tstamp' => Http::response(['code' => 'ok', 'message' => '', 'response' => $datalog]),
        $base.'monitor_jobqueue_count' => Http::response(['code' => 'ok', 'message' => '', 'response' => $queue]),
    ]);
    $asked = [];
}

/** One ordinary write: the PHP version of a site. */
function datalogWrite(IspConfigWebProvider $adapter): ProviderResult
{
    return $adapter->setPhpVersion(new ResourceRef('web_domain', '7', '1', ['client_id' => 3], 'srv_1'), '8.3');
}

it('fails the operation when the server says our own change could not be applied', function () {
    $adapter = datalogAdapter();
    // the queue is empty — against the old code that alone was "succeeded", whatever the server had done with the job
    datalogFake([
        ['datalog_id' => 500, 'server_id' => 1, 'dbtable' => 'web_domain', 'dbidx' => 'domain_id:7', 'status' => 'error', 'error' => 'php-fpm 8.3 is not installed on this server'],
    ], queue: 0);

    $result = datalogWrite($adapter);
    expect($result->isAsync())->toBeTrue()->and($result->async->meta['datalog'])->toMatchArray(['dbtable' => 'web_domain', 'dbidx' => 'domain_id:7']);

    $status = $adapter->awaitStatus($result->async);
    expect($status->state)->toBe(AsyncStatus::FAILED)->and($status->message)->toContain('php-fpm 8.3 is not installed');
});

it('finishes as soon as our own change is applied, however busy the server is', function () {
    $adapter = datalogAdapter();
    // eleven other jobs are queued: against the old code this waited until the timeout, although ours was long done
    datalogFake([
        ['datalog_id' => 498, 'server_id' => 1, 'dbtable' => 'web_domain', 'dbidx' => 'domain_id:9', 'status' => 'pending', 'error' => ''], // somebody else's site
        ['datalog_id' => 501, 'server_id' => 1, 'dbtable' => 'web_domain', 'dbidx' => 'domain_id:7', 'status' => 'ok', 'error' => ''],
    ], queue: 11);

    $status = $adapter->awaitStatus(datalogWrite($adapter)->async);
    expect($status->state)->toBe(AsyncStatus::SUCCEEDED)->and($status->detail['datalog_id'])->toBe(501);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'monitor_jobqueue_count')); // our own row answered the question
});

it('waits while our change is still pending, and is not fooled by another record of the same server', function () {
    $adapter = datalogAdapter();
    datalogFake([
        ['datalog_id' => 502, 'server_id' => 1, 'dbtable' => 'web_domain', 'dbidx' => 'domain_id:7', 'status' => 'pending', 'error' => ''],
        ['datalog_id' => 503, 'server_id' => 1, 'dbtable' => 'mail_user', 'dbidx' => 'mailuser_id:7', 'status' => 'ok', 'error' => ''], // same number, another table
        ['datalog_id' => 504, 'server_id' => 1, 'dbtable' => 'web_domain', 'dbidx' => 'domain_id:70', 'status' => 'ok', 'error' => ''], // another site
    ], queue: 0);

    expect($adapter->awaitStatus(datalogWrite($adapter)->async)->state)->toBe(AsyncStatus::RUNNING);
});

it('keeps the old behaviour where the change log cannot be followed', function () {
    // an older panel, or a remote user without that function group: the nightly probe found no status field
    $adapter = datalogAdapter(fields: ['datalog_id', 'dbtable', 'dbidx']);
    datalogFake([['datalog_id' => 505, 'dbtable' => 'web_domain', 'dbidx' => 'domain_id:7', 'status' => 'error', 'error' => 'ignored']], queue: 0);
    expect($adapter->awaitStatus(datalogWrite($adapter)->async)->state)->toBe(AsyncStatus::SUCCEEDED); // the empty queue, as before

});

it('never asks the change log of a panel it has not probed', function () {
    $adapter = datalogAdapter(fields: []);
    datalogFake([], queue: 4);
    expect($adapter->awaitStatus(datalogWrite($adapter)->async)->state)->toBe(AsyncStatus::RUNNING); // the queue count, exactly as before
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'sys_datalog_get_by_tstamp'));
});
