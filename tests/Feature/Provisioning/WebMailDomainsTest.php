<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionDepth;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\MailProvider;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * A web hosting answers for more than one name: the site's own domain and the further names it serves. The platform
 * lets the customer make an address in any of them (`MailAddresses`) — and then made the mail domain for the SITE's
 * name whatever the address said, so the node never accepted mail for the second domain: a mailbox that looked made
 * and received nothing. Everything that walks a service's mail then took the first mail domain and stopped there, so
 * a second domain's mailboxes were not listed (and so not counted against the plan), not stopped when the service
 * was suspended, and not archived before it was removed.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** ISPConfig that remembers which domains it was asked to make mail for, and gives each of them a number. */
function mailDomainsPanel(array &$made, array &$updates): void
{
    Http::fake(function (Request $request) use (&$made, &$updates) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $data = $request->data();
        if ($function === 'mail_domain_add') {
            $made[] = mb_strtolower((string) data_get($data, 'params.domain', ''));
        }
        if ($function === 'mail_user_update') {
            $updates[] = ['id' => (int) ($data['primary_id'] ?? 0), 'params' => (array) ($data['params'] ?? [])];
        }
        $boxes = [
            'shop.cz' => [['mailuser_id' => 11, 'email' => 'info@shop.cz', 'name' => 'Info', 'quota' => 2147483648, 'postfix' => 'y', 'disabledeliver' => 'n', 'disablesmtp' => 'n', 'password' => 'hashed', 'sys_userid' => 1]],
            'shop.sk' => [['mailuser_id' => 21, 'email' => 'info@shop.sk', 'name' => 'Info SK', 'quota' => 1073741824, 'postfix' => 'y', 'disabledeliver' => 'n', 'disablesmtp' => 'n', 'password' => 'hashed', 'sys_userid' => 1]],
        ];
        $pattern = (string) data_get($data, 'primary_id.email', '');
        $asked = ltrim(mb_strtolower($pattern), '%@');

        return Http::response(match (true) {
            $function === 'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-md'],
            $function === 'mail_domain_get' => ['code' => 'ok', 'message' => '', 'response' => []],
            $function === 'sites_web_domain_get' => ['code' => 'ok', 'message' => '', 'response' => ['domain_id' => 7, 'domain' => 'shop.cz', 'client_id' => 3, 'sys_groupid' => 3, 'active' => 'y', 'system_user' => 'web7', 'document_root' => '/var/www/clients/client3/web7', 'hd_quota' => 51200, 'fastcgi_php_version' => 'PHP 8.3']],
            $function === 'mail_domain_add' => ['code' => 'ok', 'message' => '', 'response' => 900 + count($made)],
            $function === 'client_get_by_username' => ['code' => 'ok', 'message' => '', 'response' => ['client_id' => 3, 'username' => 'onh_1']],
            $function === 'mail_user_add' => ['code' => 'ok', 'message' => '', 'response' => 5001],
            $function === 'mail_user_get' && $pattern !== '' => ['code' => 'ok', 'message' => '', 'response' => $boxes[$asked] ?? []],
            $function === 'mail_user_get' => ['code' => 'ok', 'message' => '', 'response' => collect($boxes)->flatten(1)->firstWhere('mailuser_id', (int) ($data['primary_id'] ?? 0)) ?? []],
            $function === 'monitor_jobqueue_count' => ['code' => 'ok', 'message' => '', 'response' => 0],
            default => ['code' => 'ok', 'message' => '', 'response' => []],
        });
    });
}

/** The site of a web service and the second name it answers for. */
function siteWithAlias(Service $service): void
{
    Website::query()->create(['service_id' => $service->id, 'domain' => 'shop.cz', 'aliases' => ['shop.sk'], 'executor' => 'ispconfig', 'php_version' => '8.3',
        'remote_client_id' => 3, 'remote_site_id' => 7, 'remote_node' => '1', 'system_user' => 'web7', 'state' => 'active']);
}

/** A mail domain the service already has. */
function mailBindingFor(Service $service, string $domain, string $remoteId): ProviderBinding
{
    MailDomain::query()->updateOrCreate(['service_id' => $service->id, 'domain' => $domain], ['remote_id' => (int) $remoteId, 'remote_node' => '1', 'remote_client_id' => 3, 'sending_enabled' => true, 'state' => 'active']);

    return ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $service->provider_instance_id, 'remote_type' => 'mail_domain',
        'remote_id' => $remoteId, 'remote_node' => '1', 'meta' => ['domain' => $domain, 'client_id' => 3], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "md:{$service->id}:{$domain}", 'adapter_version' => '1.0.0']);
}

it('makes the mail domain of the address, not of the site', function () {
    [$made, $updates] = [[], []];
    mailDomainsPanel($made, $updates);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    siteWithAlias($service);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'wmd-1', ['address' => 'info@shop.sk', 'password' => 'Correct-Horse-Battery-9', 'quota_mb' => 1024]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''))
        ->and($made)->toBe(['shop.sk'])   // the node accepts mail for the domain the address is in
        ->and(MailDomain::query()->where('service_id', $service->id)->pluck('domain')->all())->toBe(['shop.sk'])
        ->and(ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'mail_domain')->value('meta'))->toMatchArray(['domain' => 'shop.sk']);
});

it('gives the second domain a mail domain of its own', function () {
    [$made, $updates] = [[], []];
    mailDomainsPanel($made, $updates);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    siteWithAlias($service);
    mailBindingFor($service, 'shop.cz', '909'); // mail for the site's own domain is already running

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'wmd-2', ['address' => 'info@shop.sk', 'password' => 'Correct-Horse-Battery-9', 'quota_mb' => 1024]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''))
        ->and($made)->toBe(['shop.sk'])
        ->and(ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'mail_domain')->count())->toBe(2);
});

it('lists the mailboxes of every domain the service has mail in', function () {
    [$made, $updates] = [[], []];
    mailDomainsPanel($made, $updates);
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    mailBindingFor($service, 'shop.cz', '909');
    mailBindingFor($service, 'shop.sk', '910');

    $boxes = app(ServiceFeatures::class)->resources($service, 'mailboxes');

    expect(array_column($boxes, 'address'))->toBe(['info@shop.cz', 'info@shop.sk']); // and so the plan's number is counted over both
});

it('stops every domain of a suspended service from sending', function () {
    [$made, $updates] = [[], []];
    mailDomainsPanel($made, $updates);
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    mailBindingFor($service, 'shop.cz', '909');
    mailBindingFor($service, 'shop.sk', '910');
    [$adapter, $ref] = [app(ServiceFeatures::class)->adapterFor($service), app(ServiceFeatures::class)->refFor($service)];

    $paused = app(SuspensionDepth::class)->pause($service->refresh(), $adapter, $ref);

    expect($paused['mail'])->toBe(['11', '21'])
        ->and(array_column($updates, 'id'))->toBe([11, 21])
        ->and(collect($updates)->firstWhere('id', 21)['params']['disablesmtp'])->toBe('y');
});

it('switches sending off in every domain when the customer asks for it', function () {
    [$made, $updates] = [[], []];
    mailDomainsPanel($made, $updates);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    mailBindingFor($service, 'shop.cz', '909');
    mailBindingFor($service, 'shop.sk', '910');

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'sending.set', $this->contextFor($user, $org), 'wmd-3', ['enabled' => false]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''))
        ->and(array_column($updates, 'id'))->toBe([11, 21]) // not only the first domain's mailboxes
        ->and(collect($updates)->firstWhere('id', 21)['params']['disablesmtp'])->toBe('y');
});

it('archives every mail domain before the service is removed', function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    mailBindingFor($service, 'shop.cz', '909');
    mailBindingFor($service, 'shop.sk', '910');

    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $transport->shouldReceive('archive')->andReturnNull();
    $transport->shouldReceive('download')->andReturnUsing(fn (string $path, string $local) => file_put_contents($local, gzencode(random_bytes(4096))));
    $adapter = Mockery::mock(WebHostingProvider::class, WebToolsProvider::class, MailProvider::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('transport')->andReturn($transport);
    $adapter->shouldReceive('listDatabases')->andReturn([]);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'shop.cz', 'system_user' => 'web7'], 'active', now()->toISOString()));
    // each mail domain is asked for its own mailboxes
    $adapter->shouldReceive('listMailboxes')->andReturnUsing(fn ($ref) => [['remote_id' => $ref->remoteId === '909' ? '11' : '21', 'address' => 'info@'.($ref->meta['domain'] ?? ''), 'name' => 'Info', 'quota_mb' => 2048, 'used_mb' => null, 'active' => true]]);
    $adapter->shouldReceive('listAliases')->andReturn([]);
    $adapter->shouldReceive('dkim')->andReturn(['selector' => 'onhost202609', 'public' => 'MIIBIjAN']);

    $archive = app(FinalArchive::class)->create($service->refresh(), $adapter, $service->primaryBinding()->ref(), CommandContext::system('test'));

    expect(array_keys($archive['parts']))->toContain('mail-domain.json')->toContain('mail-domain-shop.sk.json');
    $second = json_decode((string) Storage::disk(app(FinalArchive::class)->disk()->getConfig()['driver'] ?? 'local')->get($archive['set'].'/mail-domain-shop.sk.json') ?: '{}', true);
    expect($second['mailboxes'][0]['address'] ?? null)->toBe('info@shop.sk');
});
