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

/**
 * An ISPConfig that answers out of a little panel of its own: `$panel` is the state and every write changes it, so a
 * test can look at what is left on the node instead of at the calls. A function nothing here answers is a fault, so a
 * call the adapter should not be making fails the test out loud. `$panel['refuse']` names functions that always refuse.
 *
 * @param  array<string,mixed>  $panel
 */
function ispPanelFake(array &$panel): void
{
    Http::fake(function ($request) use (&$panel) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $key = $request->data()['primary_id'] ?? null;
        $id = is_array($key) ? 0 : (int) $key;
        $matching = fn (array $bag) => array_values(array_filter($bag, function (array $row) use ($key) {
            foreach (is_array($key) ? $key : [] as $column => $value) {
                if ((int) ($row[$column] ?? 0) !== (int) $value) {
                    return false;
                }
            }

            return true;
        }));
        if (in_array($function, (array) ($panel['refuse'] ?? []), true)) {
            return Http::response(ispResponse(false, 'remote_fault', 'the panel refused this'));
        }
        $drop = function (string $bag) use (&$panel, $id) {
            unset($panel[$bag][$id]);

            return ispResponse(1);
        };

        return Http::response(match ($function) {
            'login' => ispResponse('sess-term'),
            'sites_web_domain_get' => ispResponse($panel['site']),
            'sites_web_aliasdomain_get' => ispResponse($matching($panel['alias'])),
            'sites_web_subdomain_get' => ispResponse($matching($panel['sub'])),
            'sites_database_get' => ispResponse($matching($panel['db'])),
            'sites_database_user_get' => ispResponse($panel['dbuser'][$id] ?? false),
            'sites_ftp_user_get' => ispResponse($matching($panel['ftp'])),
            'sites_shell_user_get' => ispResponse($matching($panel['shell'])),
            'sites_cron_get' => ispResponse($matching($panel['cron'])),
            'sites_web_aliasdomain_delete' => $drop('alias'),
            'sites_web_subdomain_delete' => $drop('sub'),
            'sites_database_delete' => $drop('db'),
            'sites_database_user_delete' => $drop('dbuser'),
            'sites_ftp_user_delete' => $drop('ftp'),
            'sites_shell_user_delete' => $drop('shell'),
            'sites_cron_delete' => $drop('cron'),
            'sites_web_domain_delete' => (function () use (&$panel) {
                $panel['site_deleted'] = true;

                return ispResponse(1);
            })(),
            'monitor_jobqueue_count' => ispResponse(0),
            default => ispResponse(false, 'remote_fault', "nothing here answers {$function}"),
        });
    });
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

it('moves the limits of the client with the plan, and leaves the other sites their share of the space', function () {
    // the client's limits were written once, when the client was created: a customer who paid for „10 webů“ and 50 GB
    // still had `limit_web_domain = 1` at the panel, so ISPConfig refused the second site and the bigger quota
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-plan')),
        'shared01.mgmt.test:8080/remote/json.php?client_get' => Http::response(ispResponse([
            'client_id' => 12, 'username' => 'onh_1', 'password' => '$1$hashed', 'contact_name' => 'ONhost customer', 'sys_userid' => 1,
            'limit_web_domain' => 1, 'limit_web_quota' => 10240, 'limit_database' => 1, 'limit_mailbox' => 5, 'limit_cron' => 1, 'limit_shell_user' => 0,
        ])),
        'shared01.mgmt.test:8080/remote/json.php?client_update' => Http::response(ispResponse(1)),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::response(ispResponse(['domain_id' => 77, 'domain' => 'shop.cz', 'hd_quota' => 10240, 'pm_max_children' => 2, 'ssl_cert' => 'keep-out', 'sys_userid' => 1])),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_update' => Http::response(ispResponse(1)),
        'shared01.mgmt.test:8080/remote/json.php?monitor_jobqueue_count' => Http::response(ispResponse(0)),
    ]);
    $adapter = ispAdapter();
    $ref = new ResourceRef('web_domain', '77', '1', ['client_id' => 12, 'domain' => 'shop.cz'], 'srv_web');
    // the plan sells 50 GB; the customer's other site of the plan holds 20, so this one gets 30
    $result = $adapter->resize($ref, new ResourceSpec('srv_web', 'website', 'plan-change:1', [
        'entitlements' => ['sites' => 10, 'nvme_gb' => 50, 'databases' => 20, 'mailboxes' => 50, 'cron_concurrency' => 2, 'ssh' => true, 'php_workers' => 6],
        'site_nvme_gb' => 30,
    ], organizationId: 'org_1'));

    expect($result->data['client_limits'])->toContain('limit_web_domain')->toContain('limit_web_quota');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?client_update') && (int) $r['client_id'] === 12
        && (int) $r['params']['limit_web_domain'] === 10 && (int) $r['params']['limit_web_quota'] === 51200
        && (int) $r['params']['limit_database'] === 20 && (int) $r['params']['limit_shell_user'] === 1
        && ! isset($r->data()['params']['password']) && ! isset($r->data()['params']['sys_userid'])); // the hash would become the new password
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?sites_web_domain_update') && (int) $r['params']['hd_quota'] === 30 * 1024 && (int) $r['params']['pm_max_children'] === 6);
});

it('does not fail a plan change when the panel refuses the client functions', function () {
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-plan2')),
        'shared01.mgmt.test:8080/remote/json.php?client_get' => Http::response(ispResponse(false, 'remote_fault', 'You do not have the permissions to access this function.')),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::response(ispResponse(['domain_id' => 77, 'domain' => 'shop.cz', 'hd_quota' => 10240, 'sys_userid' => 1])),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_update' => Http::response(ispResponse(1)),
        'shared01.mgmt.test:8080/remote/json.php?monitor_jobqueue_count' => Http::response(ispResponse(0)),
    ]);
    $result = ispAdapter()->resize(new ResourceRef('web_domain', '77', '1', ['client_id' => 12], 'srv_web'), new ResourceSpec('srv_web', 'website', 'plan-change:2', ['entitlements' => ['sites' => 10, 'nvme_gb' => 50]], organizationId: 'org_1'));

    expect($result->data['client_limits'][0] ?? '')->toContain('refused'); // said out loud, not swallowed
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?sites_web_domain_update') && (int) $r['params']['hd_quota'] === 51200);
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '?client_update'));
});

/*
 * One ISPConfig client per organization, so its limits are the ORGANIZATION's.
 *
 * `ensureClient` keys the client on the organization and, when it finds one, hands back its id and touches nothing.
 * The limits were therefore whatever the FIRST service of that customer happened to sell. Two ordinary web hostings
 * (`sites = 1` each) leave the client at `limit_web_domain = 1`, so ISPConfig refuses the second
 * `sites_web_domain_add` — **the customer paid and the order fails at the panel**. The same arithmetic applies to the
 * quota, the databases and the mailboxes.
 *
 * The platform therefore sends what the organization holds on this panel (`client_entitlements`) beside what this one
 * service sells (`entitlements`), and the client is brought up to it before a site is added.
 */

it('raises the client limits to what the organization holds before adding the second hosting', function () {
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-second')),
        'shared01.mgmt.test:8080/remote/json.php?client_get_by_username' => Http::response(ispResponse(['client_id' => 12, 'username' => 'onh_1'])),
        'shared01.mgmt.test:8080/remote/json.php?client_get' => Http::response(ispResponse([
            'client_id' => 12, 'username' => 'onh_1', 'password' => '$1$hashed', 'contact_name' => 'ONhost customer', 'sys_userid' => 1,
            'limit_web_domain' => 1, 'limit_web_quota' => 10240, 'limit_database' => 1, 'limit_mailbox' => 5, 'limit_cron' => 1, 'limit_shell_user' => 0,
        ])),
        'shared01.mgmt.test:8080/remote/json.php?client_update' => Http::response(ispResponse(1)),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::sequence()
            ->push(ispResponse([]))                                                                   // findSite: the name is not there yet
            ->push(ispResponse(['domain_id' => 78, 'domain' => 'druhy.cz', 'system_user' => 'web78'])),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_add' => Http::response(ispResponse(78)),
        'shared01.mgmt.test:8080/remote/json.php?monitor_jobqueue_count' => Http::response(ispResponse(0)),
    ]);

    ispAdapter()->provision(new ResourceSpec('srv_second', 'website', 'prov:second', [
        'domain' => 'druhy.cz', 'php_version' => '8.3',
        'entitlements' => ['sites' => 1, 'nvme_gb' => 10, 'databases' => 1, 'mailboxes' => 5],           // what THIS hosting sells
        'client_entitlements' => ['sites' => 2, 'nvme_gb' => 20, 'databases' => 2, 'mailboxes' => 10],   // what the organization holds here
    ], organizationId: 'org_1'));

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?client_update') && (int) $r['client_id'] === 12
        && (int) $r['params']['limit_web_domain'] === 2 && (int) $r['params']['limit_web_quota'] === 20480
        && (int) $r['params']['limit_database'] === 2 && (int) $r['params']['limit_mailbox'] === 10);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?sites_web_domain_add') && (int) $r['client_id'] === 12);
});

it('creates a new client with what the organization holds, not with one plan', function () {
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-new')),
        'shared01.mgmt.test:8080/remote/json.php?client_get_by_username' => Http::response(ispResponse(false, 'remote_fault', 'There is no user account for this user name.')),
        'shared01.mgmt.test:8080/remote/json.php?client_add' => Http::response(ispResponse(44)),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::sequence()
            ->push(ispResponse([]))
            ->push(ispResponse(['domain_id' => 79, 'domain' => 'prvni.cz', 'system_user' => 'web79'])),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_add' => Http::response(ispResponse(79)),
        'shared01.mgmt.test:8080/remote/json.php?monitor_jobqueue_count' => Http::response(ispResponse(0)),
    ]);

    ispAdapter()->provision(new ResourceSpec('srv_first', 'website', 'prov:first', [
        'domain' => 'prvni.cz', 'php_version' => '8.3',
        'entitlements' => ['sites' => 1, 'nvme_gb' => 10, 'databases' => 1],
        'client_entitlements' => ['sites' => 3, 'nvme_gb' => 40, 'databases' => 6, 'mailboxes' => 20],
    ], organizationId: 'org_2'));

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?client_add')
        && (int) $r['params']['limit_web_domain'] === 3 && (int) $r['params']['limit_web_quota'] === 40960
        && (int) $r['params']['limit_database'] === 6 && (int) $r['params']['limit_mailbox'] === 20);
});

it('falls back to the service plan when the platform sends no organization totals', function () {
    Http::fake([
        'shared01.mgmt.test:8080/remote/json.php?login' => Http::response(ispResponse('sess-fallback')),
        'shared01.mgmt.test:8080/remote/json.php?client_get_by_username' => Http::response(ispResponse(false, 'remote_fault', 'There is no user account for this user name.')),
        'shared01.mgmt.test:8080/remote/json.php?client_add' => Http::response(ispResponse(45)),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_get' => Http::sequence()
            ->push(ispResponse([]))
            ->push(ispResponse(['domain_id' => 80, 'domain' => 'stary.cz', 'system_user' => 'web80'])),
        'shared01.mgmt.test:8080/remote/json.php?sites_web_domain_add' => Http::response(ispResponse(80)),
        'shared01.mgmt.test:8080/remote/json.php?monitor_jobqueue_count' => Http::response(ispResponse(0)),
    ]);

    ispAdapter()->provision(new ResourceSpec('srv_old', 'website', 'prov:old', [
        'domain' => 'stary.cz', 'php_version' => '8.3', 'entitlements' => ['sites' => 5, 'nvme_gb' => 25, 'databases' => 4],
    ], organizationId: 'org_3'));

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '?client_add') && (int) $r['params']['limit_web_domain'] === 5 && (int) $r['params']['limit_web_quota'] === 25600);
});

/*
 * Deleting a site has to take the customer's data and their access with it.
 *
 * `sites_web_domain_delete` deletes one row: the vhost. The databases live in `web_database`, their users in
 * `web_database_user`, the FTP accounts in `ftp_user`, the SSH accounts in `shell_user`, the jobs in `cron` and the
 * further host names in `web_domain` rows of their own — each of them pointing at the site by `parent_domain_id`, none
 * of them named in that one call. Whether the panel cleans them up behind the API is not something the platform may
 * assume: what it created, it removes itself, and every delete below is scoped to this site's own children.
 *
 * What was left behind is a terminated customer's data on a live node long past every retention promise, their FTP,
 * SSH and database passwords still working on a shared machine, the disk never freed — and an alias vhost still
 * answering for a host name the platform believes nobody holds. The aaPanel adapter has always done this (it drops
 * the cron jobs and the Node apps before `DeleteSite`, which itself takes the files, databases and FTP accounts).
 */

it('takes the customer\'s data and access with the site: databases, users, FTP, SSH, cron and further names', function () {
    $panel = [
        'site' => ['domain_id' => 77, 'domain' => 'shop.cz', 'system_user' => 'web77', 'document_root' => '/var/www/clients/client12/web77'],
        'alias' => [5 => ['domain_id' => 5, 'domain' => 'shop-old.cz', 'parent_domain_id' => 77]],
        'sub' => [9 => ['domain_id' => 9, 'domain' => 'blog.shop.cz', 'redirect_path' => '/blog/', 'parent_domain_id' => 77]],
        'db' => [3 => ['database_id' => 3, 'database_name' => 'c12_shop', 'database_user_id' => 2, 'parent_domain_id' => 77]],
        'dbuser' => [2 => ['database_user_id' => 2, 'database_user' => 'c12_shop']],
        'ftp' => [4 => ['ftp_user_id' => 4, 'username' => 'web77_ftp', 'parent_domain_id' => 77, 'dir' => '/var/www/clients/client12/web77/web']],
        'shell' => [6 => ['shell_user_id' => 6, 'username' => 'web77_ssh', 'parent_domain_id' => 77, 'ssh_rsa' => 'ssh-ed25519 AAAA']],
        'cron' => [8 => ['id' => 8, 'command' => '/usr/bin/php /var/www/cron.php', 'parent_domain_id' => 77, 'run_min' => '5']],
    ];
    ispPanelFake($panel);

    $result = ispAdapter()->terminate(new ResourceRef('web_domain', '77', '1', ['domain' => 'shop.cz', 'system_user' => 'web77', 'client_id' => 12], 'srv_shop'));

    expect($panel['site_deleted'] ?? false)->toBeTrue()      // the site itself goes, as it always did
        ->and($panel['db'])->toBe([])                        // and the customer's database with it
        ->and($panel['dbuser'])->toBe([])                    // including the login that reached it
        ->and($panel['ftp'])->toBe([])
        ->and($panel['shell'])->toBe([])                     // a shell user is a system account on a shared node
        ->and($panel['cron'])->toBe([])
        ->and($panel['alias'])->toBe([])                     // an alias vhost went on answering for a name nobody claimed
        ->and($panel['sub'])->toBe([])
        ->and($result->data['leftover'] ?? [])->toBe([]);
});

it('deletes the site even when the panel refuses one of its children, and says exactly what is left', function () {
    $panel = [
        'site' => ['domain_id' => 77, 'domain' => 'shop.cz', 'system_user' => 'web77'],
        'alias' => [], 'sub' => [],
        'db' => [3 => ['database_id' => 3, 'database_name' => 'c12_shop', 'database_user_id' => 2, 'parent_domain_id' => 77]],
        'dbuser' => [2 => ['database_user_id' => 2, 'database_user' => 'c12_shop']],
        'ftp' => [4 => ['ftp_user_id' => 4, 'username' => 'web77_ftp', 'parent_domain_id' => 77]],
        'shell' => [], 'cron' => [],
        'refuse' => ['sites_database_delete'],
    ];
    ispPanelFake($panel);

    $result = ispAdapter()->terminate(new ResourceRef('web_domain', '77', '1', ['domain' => 'shop.cz', 'system_user' => 'web77', 'client_id' => 12], 'srv_shop'));

    // the service ends whatever the panel says — what is left is named, not swallowed, and stays findable by id
    expect($panel['site_deleted'] ?? false)->toBeTrue()
        ->and($panel['ftp'])->toBe([])
        ->and(array_keys($panel['db']))->toBe([3])
        ->and(array_keys($panel['dbuser']))->toBe([2]) // the login still owns a database, so it is not deleted either
        ->and($result->data['leftover']['database'] ?? [])->toBe(['3'])
        ->and($result->data['leftover']['db_user'] ?? [])->toBe(['2']);
});
