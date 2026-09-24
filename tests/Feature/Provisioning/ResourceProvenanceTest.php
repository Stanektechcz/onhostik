<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Mail\MailDomains;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\IspConfig\IspConfigWebProvider;

/*
 * „Do již existujících webů na ISPconfig a AAPanel nesmíme nijak zasahovat při vývoji. Toto jsou historické weby do
 * kterých nový systém nesmí jakkoliv zasahovat.“ — the owner, 2026-09-24.
 *
 * The live panels hold sites that were made by hand long before this platform. Provisioning was idempotent in the
 * friendliest way possible: it looked the name up on the node and, when a site of that name was already there,
 * answered "already exists" and used it. The saga then bound the service to it as `managed_by: onhost` — every
 * binding is written that way, and nothing ever reads it — and from that moment the platform could suspend that
 * site, change its quota and PHP workers, switch off its cron, and at the end of the service delete it with its
 * databases. The guards of the deletion did not help: the domain matched and so did the site's own system user.
 * All it took was a customer ordering hosting for a name that already lived on the node.
 *
 * A resource that was already there is ours only when the panel itself says we made it: on aaPanel the remark the
 * platform writes into every site it creates (`onhost:<service>`), on ISPConfig the client the platform created for
 * the organization (`onh_…`) owning the site or the mail domain. Anything else is historical and is refused before
 * a single write — adopting it is an explicit act of its own (brain H304), never a side effect of an order.
 */

beforeEach(fn () => Http::preventStrayRequests());

function provenanceIsp(): IspConfigWebProvider
{
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost-remote';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'remote-secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'options' => ['server_id' => 1, 'verify_tls' => false]]);
    $registry = app(ProviderRegistry::class);
    $registry->register('ispconfig', IspConfigWebProvider::class);

    return $registry->forInstance($instance);
}

function provenanceAa(): AaPanelWebProvider
{
    $_ENV['AAPANEL_MANAGED01_API_KEY'] = 'aa-key-123';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-managed01'], ['provider' => 'aapanel', 'name' => 'aaPanel managed01', 'base_url' => 'https://managed01.mgmt.test:8888', 'secret_ref' => 'env://AAPANEL_MANAGED01', 'state' => 'active', 'options' => ['verify_tls' => false]]);
    $registry = app(ProviderRegistry::class);
    $registry->register('aapanel', AaPanelWebProvider::class);

    return $registry->forInstance($instance);
}

function provenanceOk(mixed $response): array
{
    return ['code' => 'ok', 'message' => '', 'response' => $response];
}

/**
 * An ISPConfig holding one site for `shop.cz`, owned by client `$owner`; our organization's client is 12 (group 14).
 *
 * @param  list<string>  $calls
 */
function provenanceIspPanel(array &$calls, int $owner, ?int $mailGroup = null): void
{
    Http::fake(function (Request $request) use (&$calls, $owner, $mailGroup) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = $function;

        return Http::response(match ($function) {
            'login' => provenanceOk('sess-prov'),
            'sites_web_domain_get' => provenanceOk([['domain_id' => 55, 'domain' => 'shop.cz', 'sys_groupid' => $owner + 2, 'system_user' => 'web55', 'system_group' => "client{$owner}", 'document_root' => "/var/www/clients/client{$owner}/web55"]]),
            'mail_domain_get' => provenanceOk($mailGroup === null ? [] : [['domain_id' => 77, 'domain' => 'shop.cz', 'sys_groupid' => $mailGroup, 'active' => 'y']]),
            'client_get_by_username' => provenanceOk(['client_id' => 12, 'username' => 'onh_org1']),
            'client_get_groupid' => provenanceOk(14),
            'monitor_jobqueue_count' => provenanceOk(0),
            default => provenanceOk([]),
        });
    });
}

it('refuses to take over an ISPConfig site that was on the node before the platform', function () {
    $calls = [];
    provenanceIspPanel($calls, owner: 9); // made by hand under somebody else's client

    try {
        provenanceIsp()->provision(new ResourceSpec('srv_new', 'website', 'ord-9:provision.web:v1', ['domain' => 'shop.cz', 'entitlements' => ['sites' => 1]], organizationId: 'org_1'));
        $this->fail('a historical site must not be adopted');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::CONFLICT)->and($e->getMessage())->toContain('shop.cz');
    }
    // not a single write: no site, no client created, no client limits "brought up to date"
    expect(array_values(array_intersect($calls, ['sites_web_domain_add', 'sites_web_domain_update', 'client_add', 'client_update'])))->toBe([]);
});

it('still finds its own ISPConfig site again when a provisioning is retried', function () {
    $calls = [];
    provenanceIspPanel($calls, owner: 12); // the organization's own client: the platform made this site

    $result = provenanceIsp()->provision(new ResourceSpec('srv_new', 'website', 'ord-9:provision.web:v1', ['domain' => 'shop.cz', 'entitlements' => ['sites' => 1]], organizationId: 'org_1'));

    expect($result->alreadyExisted)->toBeTrue()->and($result->ref?->remoteId)->toBe('55')
        ->and(array_values(array_intersect($calls, ['sites_web_domain_add', 'client_add'])))->toBe([]);
});

it('refuses to take over an ISPConfig mail domain it did not make', function () {
    $calls = [];
    provenanceIspPanel($calls, owner: 12, mailGroup: 3); // somebody's mail, under group 3; ours is group 14

    try {
        provenanceIsp()->createMailDomain(new ResourceSpec('srv_new', 'mail_domain', 'ord-9:mail:v1', ['domain' => 'shop.cz'], organizationId: 'org_1'));
        $this->fail('a historical mail domain must not be adopted');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::CONFLICT);
    }
    expect(array_values(array_intersect($calls, ['mail_domain_add', 'mail_domain_update', 'client_add'])))->toBe([]);
});

/*
 * The same rule, reached from the other side. A mail service is bound to its mail domain with the meta the adapter
 * returned — and that meta carried no domain name. Every mailbox query is `LIKE '%@<domain>'`, so with the name
 * missing it read `'%@'`: every mailbox on the shared mail server. A suspended mail customer switched off sending
 * for all of them, historical customers included, and the ownership checks that compare a mailbox id with that
 * listing let a customer act on any mailbox on the server.
 */

it('never asks the mail server for every mailbox when a mail domain has no name on record', function () {
    $queries = [];
    $answerName = true;
    Http::fake(function (Request $request) use (&$queries, &$answerName) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        if (in_array($function, ['mail_user_get', 'mail_alias_get'], true)) {
            $queries[] = (string) data_get($request->data(), 'primary_id.email', data_get($request->data(), 'primary_id.source', ''));
        }

        return Http::response(match ($function) {
            'login' => provenanceOk('sess-mail'),
            'mail_domain_get' => provenanceOk($answerName ? ['domain_id' => 77, 'domain' => 'shop.cz'] : false),
            default => provenanceOk([]),
        });
    });
    $bare = new ResourceRef('mail_domain', '77', '1', ['client_id' => 12], 'srv_mail'); // as the mail saga bound it

    provenanceIsp()->listMailboxes($bare);
    provenanceIsp()->setSendingEnabled($bare, false);
    expect($queries)->not->toContain('%@')->and($queries)->toContain('%@shop.cz');

    // and when the panel cannot name the domain either, nothing is asked at all
    $answerName = false;
    $queries = [];
    expect(fn () => provenanceIsp()->setSendingEnabled($bare, false))->toThrow(ProviderException::class);
    expect($queries)->toBe([]);
});

it('shows a web hosting without a mail domain no mailboxes at all, instead of every mailbox on the server', function () {
    $asked = false;
    Http::fake(function (Request $request) use (&$asked) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $asked = $asked || in_array($function, ['mail_user_get', 'mail_alias_get'], true);

        return Http::response(provenanceOk($function === 'login' ? 'sess-web' : [['mailuser_id' => 9001, 'email' => 'boss@historical-shop.cz']]));
    });
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'ispconfig'); // its binding carries no domain name, and it has no mail domain yet

    $listed = MailDomains::across($site, $site->primaryBinding()->ref(), fn (ResourceRef $one) => provenanceIsp()->listMailboxes($one));

    expect($listed)->toBe([])->and($asked)->toBeFalse();
});

it('keeps the domain name on the mail domain it creates', function () {
    Http::fake(fn (Request $request) => Http::response(match ((string) parse_url($request->url(), PHP_URL_QUERY)) {
        'login' => provenanceOk('sess-mail'),
        'client_get_by_username' => provenanceOk(['client_id' => 12, 'username' => 'onh_org1']),
        'mail_domain_add' => provenanceOk(88),
        'monitor_jobqueue_count' => provenanceOk(0),
        default => provenanceOk([]),
    }));

    $created = provenanceIsp()->createMailDomain(new ResourceSpec('srv_mail', 'mail_domain', 'ord-7:mail:v1', ['domain' => 'Shop.CZ'], organizationId: 'org_1'));

    expect($created->ref?->meta['domain'] ?? null)->toBe('shop.cz');
});

it('refuses a database of the same name that hangs off another site', function () {
    $calls = [];
    Http::fake(function (Request $request) use (&$calls) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = $function;

        return Http::response(match ($function) {
            'login' => provenanceOk('sess-db'),
            'sites_database_get' => provenanceOk([['database_id' => 301, 'database_name' => 'c9_shop', 'parent_domain_id' => 40]]), // site 40 is not ours
            default => provenanceOk([]),
        });
    });
    $site = new ResourceRef('web_domain', '55', '1', ['client_id' => 12], 'srv_new');

    expect(fn () => provenanceIsp()->createDatabase($site, ['name' => 'c9_shop', 'user' => 'c9_shop', 'password' => 'Correct-Horse-9']))->toThrow(ProviderException::class, 'another site');
    expect(array_values(array_intersect($calls, ['sites_database_add', 'sites_database_user_add'])))->toBe([]);
});

it('refuses to take over an aaPanel site that does not carry the platform\'s mark, and finds its own again', function () {
    $sites = [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1', 'ps' => 'eshop pana Nováka']];
    $added = false;
    Http::fake(function (Request $request) use (&$sites, &$added) {
        if (str_contains($request->url(), 'action=AddSite')) {
            $added = true;

            return Http::response(['siteStatus' => true, 'siteId' => 99]);
        }

        return Http::response(['data' => $sites, 'page' => '']);
    });

    try {
        provenanceAa()->provision(new ResourceSpec('srv_m1', 'website', 'ord-3:provision.managed:v1', ['domain' => 'shop.cz', 'php_version' => '8.3']));
        $this->fail('a historical aaPanel site must not be adopted');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::CONFLICT);
    }
    expect($added)->toBeFalse();

    // the site this very service made (the remark AddSite writes) is found again on a retry
    $sites[0]['ps'] = 'onhost:srv_m1';
    $again = provenanceAa()->provision(new ResourceSpec('srv_m1', 'website', 'ord-3:provision.managed:v1', ['domain' => 'shop.cz', 'php_version' => '8.3']));
    expect($again->alreadyExisted)->toBeTrue()->and($again->ref?->remoteId)->toBe('41');

    // and a site another service of ours made is not this service's to take either
    $sites[0]['ps'] = 'onhost:srv_other';
    expect(fn () => provenanceAa()->provision(new ResourceSpec('srv_m1', 'website', 'ord-3:provision.managed:v1', ['domain' => 'shop.cz', 'php_version' => '8.3'])))
        ->toThrow(ProviderException::class);
});

it('fails the order before anything is bound when the name belongs to a historical site', function () {
    $calls = [];
    provenanceIspPanel($calls, owner: 9);
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    ProviderBinding::query()->where('service_id', $service->id)->delete(); // a fresh order: nothing bound yet
    $service->forceFill(['state' => ServiceStateMachine::PAID, 'activated_at' => null])->save();

    $operation = driveOperation(app(ServiceService::class)->startProvisioning($service->fresh(), CommandContext::system('order paid')->withScope($org->id)), 20);

    expect($operation->state)->toBe(Operation::FAILED)
        ->and(ProviderBinding::query()->where('service_id', $service->id)->exists())->toBeFalse() // the service was never tied to it
        ->and(array_values(array_intersect($calls, ['sites_web_domain_add', 'sites_web_domain_update', 'client_add', 'client_update'])))->toBe([]);
});
