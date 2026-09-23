<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\MailProvider;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * „5 schránek“, „50 schránek“, „500 schránek“ — every web hosting plan states a number of mailboxes, the price list
 * prints it and a paid add-on raises it. The web service offered **no mailbox action at all**: the feature list
 * carried `mail` (the number, as a label) and the actions hang off `mailboxes`, which the web branch never set. And
 * even switched on there was nowhere to put a mailbox: ISPConfig keeps mailboxes inside a mail domain, and the site
 * saga never made one — `createMailDomain()` was called only by the separate mail service's own saga.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** The ISPConfig of a web service that is about to get its first mailbox. */
function webMailPanel(array &$calls): void
{
    Http::fake(function (Request $request) use (&$calls) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = $function;

        return Http::response(match (true) {
            $function === 'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-mail'],
            $function === 'mail_domain_get' => ['code' => 'ok', 'message' => '', 'response' => []],
            $function === 'mail_domain_add' => ['code' => 'ok', 'message' => '', 'response' => 909],
            $function === 'client_get_by_username' => ['code' => 'ok', 'message' => '', 'response' => ['client_id' => 3, 'username' => 'onh_1']],
            $function === 'mail_user_get' => ['code' => 'ok', 'message' => '', 'response' => []],
            $function === 'mail_user_add' => ['code' => 'ok', 'message' => '', 'response' => 5001],
            $function === 'monitor_jobqueue_count' => ['code' => 'ok', 'message' => '', 'response' => 0],
            default => ['code' => 'ok', 'message' => '', 'response' => []],
        });
    });
}

it('offers the mailboxes its plan sells, and makes the mail domain at the first one', function () {
    $calls = [];
    webMailPanel($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig'); // the fixture's plan sells ten mailboxes

    expect(app(ServiceFeatures::class)->features($service)['mailboxes'])->toMatchArray(['enabled' => true, 'limit' => 10]);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'wm-1', ['address' => 'info@shop.cz', 'password' => 'Correct-Horse-Battery-9']), 25);
    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.' '.$operation->state.': '.(string) data_get($operation->error, 'message', ''));

    // the mail domain was made for the site's own domain, and the mailbox went into it
    expect(in_array('mail_domain_add', $calls, true))->toBeTrue()->and(in_array('mail_user_add', $calls, true))->toBeTrue()
        ->and(MailDomain::query()->where('service_id', $service->id)->value('domain'))->toBe('shop.cz')
        ->and(ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'mail_domain')->exists())->toBeTrue();

    // the records mail needs are named, whether or not the zone is ours to publish into
    $types = array_column((array) data_get($operation->fresh()->context, 'dns_records_required', []), 'type');
    expect($types)->toContain('MX')->toContain('TXT');
});

it('makes the mail domain once, not with every mailbox', function () {
    $calls = [];
    webMailPanel($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $services = app(ServiceService::class);

    driveOperation($services->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'wm-2a', ['address' => 'a@shop.cz', 'password' => 'Correct-Horse-Battery-9']), 25);
    $first = count(array_keys($calls, 'mail_domain_add', true));
    driveOperation($services->requestAction($service->fresh(), 'mailbox.create', $this->contextFor($user, $org), 'wm-2b', ['address' => 'b@shop.cz', 'password' => 'Correct-Horse-Battery-9']), 25);

    expect($first)->toBe(1)->and(count(array_keys($calls, 'mail_domain_add', true)))->toBe(1);
});

it('still refuses an address in a domain the service does not host', function () {
    $calls = [];
    webMailPanel($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');

    expect(fn () => app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'wm-3', ['address' => 'info@konkurence.cz', 'password' => 'Correct-Horse-Battery-9']))
        ->toThrow(DomainError::class, 'jen v doméně této služby');
    expect($calls)->toBe([]); // nothing was asked of the panel
});

it('does not offer mailboxes where the panel has no mail', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
    [, $org] = $this->customerWithOrganization();

    expect(app(ServiceFeatures::class)->features(featureWebService($org, 'aapanel'))['mailboxes']['enabled'] ?? false)->toBeFalse();
});

it('archives the mailboxes of a web service before anything of it is removed', function () {
    Storage::fake('local');
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    // the site already has its mail domain, as the first mailbox leaves it
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $service->provider_instance_id, 'remote_type' => 'mail_domain', 'remote_id' => '909',
        'remote_node' => '1', 'meta' => ['domain' => 'shop.cz', 'client_id' => 3], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'wm-mail:'.$service->id]);

    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $transport->shouldReceive('archive')->andReturnNull();
    $transport->shouldReceive('download')->andReturnUsing(fn (string $path, string $local) => file_put_contents($local, gzencode(random_bytes(4096))));
    $adapter = Mockery::mock(WebHostingProvider::class, WebToolsProvider::class, MailProvider::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('transport')->andReturn($transport);
    $adapter->shouldReceive('listDatabases')->andReturn([]);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => 'shop.cz', 'system_user' => 'web7'], 'active', now()->toISOString()));
    $adapter->shouldReceive('listMailboxes')->andReturn([['remote_id' => '5001', 'address' => 'info@shop.cz', 'name' => 'Info', 'quota_mb' => 2048, 'used_mb' => null, 'active' => true]]);
    $adapter->shouldReceive('listAliases')->andReturn([]);
    $adapter->shouldReceive('dkim')->andReturn(['selector' => 'onhost202609', 'public' => 'MIIBIjAN']);

    $archive = app(FinalArchive::class)->create($service->refresh(), $adapter, $service->primaryBinding()->ref(), CommandContext::system('test'));

    expect(array_keys($archive['parts']))->toContain('mail-domain.json')
        ->and(json_decode((string) Storage::disk(app(FinalArchive::class)->disk()->getConfig()['driver'] ?? 'local')->get($archive['set'].'/mail-domain.json') ?: '{}', true)['mailboxes'][0]['address'] ?? null)->toBe('info@shop.cz');
});

it('offers the panel\'s mail tools on the web service too, and makes them in the mail domain', function () {
    $calls = [];
    webMailPanel($calls);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');

    $features = app(ServiceFeatures::class)->features($service);
    foreach (['forwards', 'catchall', 'autoresponder', 'spam', 'mail_filters', 'mailing_lists', 'fetchmail', 'mail_usage'] as $tool) {
        expect($features[$tool]['enabled'] ?? false)->toBeTrue("the web service should offer {$tool}");
    }

    // a mail call is made with the mail domain's own binding: on a separate mail server the site's number is not its number
    driveOperation(app(ServiceService::class)->requestAction($service, 'alias.create', $this->contextFor($user, $org), 'wm-5', ['source' => 'sales@shop.cz', 'destination' => 'me@gmail.com']), 25);
    $binding = ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'mail_domain')->firstOrFail();
    expect($binding->remote_id)->toBe('909')->and(in_array('mail_alias_add', $calls, true))->toBeTrue();
});
