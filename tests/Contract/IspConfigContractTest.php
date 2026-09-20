<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\IspConfig\IspConfigConnector;
use Onhost\Providers\IspConfig\IspConfigWebProvider;

function ispAdapter(): IspConfigWebProvider
{
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'options' => ['server_id' => 1, 'verify_tls' => false]]);
    $registry = app(ProviderRegistry::class);
    $registry->register('ispconfig', IspConfigWebProvider::class);

    return $registry->forInstance($instance);
}

function ispResponse(mixed $response, string $code = 'ok', string $message = ''): array
{
    return ['code' => $code, 'message' => $message, 'response' => $response];
}

it('logs in once, creates client + web domain and awaits the job queue', function () {
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-123')),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::sequence()->push(ispResponse([]))->push(ispResponse(['domain_id' => 77, 'domain' => 'skladomat.cz', 'system_user' => 'web77', 'document_root' => '/var/www/clients/client12/web77'])),
        'shared01.mgmt.test:8080/remote/json.php?client_get_by_username' => Http::response(ispResponse(false)),
        'shared01.mgmt.test:8080/remote/json.php?client_add' => Http::response(ispResponse(12)),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_add' => Http::response(ispResponse(77)),
        'shared01.mgmt.test:8080/remote/json.php?monitor_jobqueue_count' => Http::sequence()->push(ispResponse(3))->push(ispResponse(0)),
    ]);
    $adapter = ispAdapter();
    $spec = new ResourceSpec('srv_01web', 'website', 'ord-2:provision.web:v1', ['domain' => 'skladomat.cz', 'php_version' => '8.3', 'entitlements' => ['sites' => 10, 'nvme_gb' => 50, 'php_workers' => 6, 'php_memory_mb' => 1024], 'organization_name' => 'Skladomat s.r.o.', 'contact_email' => 'it@skladomat.cz'], null, 'cz1', 'org_01abc');
    $result = $adapter->provision($spec);
    expect($result->isAsync())->toBeTrue()->and($result->ref->remoteId)->toBe('77')->and($result->ref->meta['client_id'])->toBe(12)->and($result->data['system_user'])->toBe('web77');
    expect($adapter->awaitStatus($result->async)->state)->toBe(AsyncStatus::RUNNING);
    expect($adapter->awaitStatus($result->async)->state)->toBe(AsyncStatus::SUCCEEDED);

    Http::assertSentCount(8);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?login') && $r['username'] === 'onhost-remote');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?sites_web_domain_add') && $r['session_id'] === 'sess-123' && $r['params']['pm_max_children'] === 6 && $r['params']['hd_quota'] === 51200 && str_contains($r['params']['custom_php_ini'], 'memory_limit = 1024M'));
    expect(DB::table('provider_calls')->where('action', 'login')->value('request'))->not->toContain('remote-secret');
});

it('reuses an existing site (idempotent) and maps remote_fault to typed errors', function () {
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-1')),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::response(ispResponse([['domain_id' => 5, 'domain' => 'a.cz', 'sys_groupid' => 3, 'system_user' => 'web5']])),
        'shared01.mgmt.test:8080/remote/json.php?client_get' => Http::response(ispResponse(false, 'remote_fault', 'You do not have the permissions to access this function.')),
        'shared01.mgmt.test:8080/remote/json.php?client_get_by_username' => Http::response(ispResponse(['client_id' => 3, 'username' => 'onh_1'])), // sys_groupid 3 of the site is a group id, the client is resolved by username
    ]);
    $adapter = ispAdapter();
    $result = $adapter->provision(new ResourceSpec('srv_x', 'website', 'k', ['domain' => 'a.cz'], organizationId: 'org_1'));
    expect($result->alreadyExisted)->toBeTrue()->and($result->ref->remoteId)->toBe('5');
    try {
        (new ReflectionClass($adapter))->getMethod('serverId'); // ensure class loaded
        app(IspConfigConnector::class, ['instance' => ProviderInstance::first(), 'credentials' => ['remote_user' => 'u', 'remote_password' => 'p']])->call('client_get', ['client_id' => 1]);
        $this->fail('expected fault');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::AUTH);
    }
});

it('creates the client when ISPConfig answers an unknown username with a fault', function () {
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-2')),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::sequence()->push(ispResponse([]))->push(ispResponse(['domain_id' => 91, 'domain' => 'novy.cz', 'system_user' => 'web91', 'document_root' => '/var/www/novy.cz'])),
        'shared01.mgmt.test:8080/remote/json.php?client_get_by_username' => Http::response(ispResponse(false, 'remote_fault', 'There is no user account for this user name.')),
        'shared01.mgmt.test:8080/remote/json.php?client_add' => Http::response(ispResponse(31)),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_add' => Http::response(ispResponse(91)),
        'shared01.mgmt.test:8080/remote/json.php?monitor_jobqueue_count' => Http::response(ispResponse(0)),
    ]);
    $result = ispAdapter()->provision(new ResourceSpec('srv_02web', 'website', 'ord-3:provision.web:v1', ['domain' => 'novy.cz', 'php_version' => '8.3', 'entitlements' => ['sites' => 1, 'nvme_gb' => 10]], organizationId: 'org_novy'));
    expect($result->ref->remoteId)->toBe('91')->and($result->ref->meta['client_id'])->toBe(31);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?client_add'));
});

it('never touches a web site when a mail service is read, suspended or terminated — the numbers of mail and web domains overlap (audit §5z)', function () {
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-123')),
        // web domain 41 exists and belongs to somebody else; mail domain 41 is the service's
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::response(ispResponse(['domain_id' => 41, 'domain' => 'cizi-web.cz', 'active' => 'n'])),
        'shared01.mgmt.test:8080/remote/json.php?mail_domain_get' => Http::sequence()->push(ispResponse(['domain_id' => 41, 'domain' => 'posta.cz', 'active' => 'y', 'dkim' => 'y', 'sys_groupid' => 9]))->push(ispResponse(['domain_id' => 41, 'domain' => 'posta.cz', 'active' => 'y', 'sys_groupid' => 9]))->push(ispResponse(['domain_id' => 41, 'domain' => 'posta.cz', 'active' => 'n', 'sys_groupid' => 9])),
        'shared01.mgmt.test:8080/remote/json.php?mail_domain_update' => Http::response(ispResponse(1)),
        'shared01.mgmt.test:8080/remote/json.php?mail_domain_delete' => Http::response(ispResponse(1)),
        'shared01.mgmt.test:8080/remote/json.php?monitor_jobqueue_count' => Http::response(ispResponse(0)),
    ]);
    $adapter = ispAdapter();
    $mail = new ResourceRef('mail_domain', '41', '1', ['client_id' => 9]);
    $state = $adapter->getActualState($mail);
    expect($state->exists)->toBeTrue()->and($state->status)->toBe('active')->and($state->get('domain'))->toBe('posta.cz');
    $adapter->suspend($mail);
    $adapter->terminate($mail);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'mail_domain_update') && $r['params']['active'] === 'n');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'mail_domain_delete') && (int) $r['primary_id'] === 41);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'sites_web_domain_delete') || str_contains($r->url(), 'sites_web_domain_update'));

    // a web reference whose stored domain does not match the site with that number is refused, and unknown types are refused
    expect(fn () => $adapter->terminate(new ResourceRef('web_domain', '41', '1', ['domain' => 'muj-web.cz'])))->toThrow(ProviderException::class, 'refusing to delete it');
    expect(fn () => $adapter->terminate(new ResourceRef('web_domain', '41', '1', ['system_user' => 'web99'])))->toThrow(ProviderException::class, 'another site user');
    expect(fn () => $adapter->terminate(new ResourceRef('database', '41', '1')))->toThrow(ProviderException::class);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'sites_web_domain_delete'));
});

it('refuses web-site calls for a mail domain: its number among web sites is somebody else\'s site', function () {
    Http::preventStrayRequests();
    Http::fake(); // nothing may reach the panel
    $adapter = ispAdapter();
    $mail = new ResourceRef('mail_domain', '42', '1', ['domain' => 'posta.cz'], 'srv_mail');
    foreach ([fn () => $adapter->listFtpAccounts($mail), fn () => $adapter->deleteFtpAccount($mail, '9'), fn () => $adapter->listShellUsers($mail), fn () => $adapter->deleteShellUser($mail, '9'), fn () => $adapter->listCron($mail), fn () => $adapter->deleteDatabase($mail, '9'), fn () => $adapter->listSubdomains($mail)] as $call) {
        expect($call)->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::VALIDATION));
    }
    Http::assertNothingSent();
});
