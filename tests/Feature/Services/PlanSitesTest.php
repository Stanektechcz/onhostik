<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\ServiceSites;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * Every web hosting plan on the price list states a number of sites — „1 web“, „10 webů“, „50 webů“ — and the agency
 * page promises „deset až padesát webů pod jedním účtem“. The customer could only ever add another DOMAIN onto the
 * same site: one document root, one PHP version, one certificate, one set of files — and the plan's number was even
 * rendered as the limit of those extra names. A further site of the plan is now a site of its own, carried by the
 * service the customer pays for, and the plan's space is divided between them (ISPConfig sums a client's site quotas
 * against the client's limit, so two sites cannot both hold the whole plan).
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

/** The panel of a service that holds several sites: the list grows with every site that is added. */
function planSitesPanel(array &$sites, string $blob): void
{
    Http::fake(function ($request) use (&$sites, $blob) {
        if (! str_contains($request->url(), 'managed01.mgmt.test')) {
            return null;
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        if (str_contains($q, 'AddSite')) {
            $name = (string) (json_decode((string) ($body['webname'] ?? '{}'), true)['domain'] ?? '');
            $id = 100 + count($sites);
            $sites[] = ['id' => $id, 'name' => $name, 'path' => '/www/wwwroot/'.$name, 'status' => '1', 'ps' => 'onhost'];

            return Http::response(['siteId' => $id, 'status' => true]);
        }

        return match (true) {
            str_contains($q, 'table=sites') => Http::response(['data' => $sites, 'page' => '']),
            str_contains($q, 'GetFileBody') => Http::response(['status' => true, 'data' => base64_encode($blob)]),
            str_contains($q, 'GetDir') => Http::response(['PATH' => (string) ($body['path'] ?? ''), 'DIR' => [], 'FILES' => ['index.php;120;1700000000;0644;www']]),
            str_contains($q, 'apply_cert_api') => Http::response(['status' => true, 'cert' => '-----BEGIN CERTIFICATE-----', 'private' => '-----BEGIN PRIVATE KEY-----']),
            default => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']),
        };
    });
}

it('hosts the sites its plan sells, each one a site of its own', function () {
    $sites = [];
    $blob = gzencode(str_repeat('site files', 50));
    planSitesPanel($sites, $blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $sites[] = ['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1', 'ps' => 'onhost'];

    // the plan sells three sites and the service has one; the customer is offered the rest
    expect(app(ServiceFeatures::class)->features($service)['sites'])->toMatchArray(['enabled' => true, 'limit' => 3])
        ->and(app(ServiceFeatures::class)->resources($service, 'sites'))->toHaveCount(1);

    $add = app(ServiceService::class)->requestAction($service, 'site.create', $this->contextFor($user, $org), 'site-1', ['domain' => 'druhy-web.cz', 'nvme_gb' => 20]);
    driveOperations(); // the new site is provisioned by its own saga; the run that added it waits for it
    expect($add->refresh()->state)->toBe(Operation::SUCCEEDED, $add->state.': '.(string) data_get($add->error, 'message', ''));

    $second = Service::query()->where('hostname', 'druhy-web.cz')->firstOrFail();
    expect($second->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(data_get($second->tags, 'parent_service_id'))->toBe($service->id)
        ->and(data_get($second->tags, 'billing'))->toBe('included')
        ->and($second->primaryBinding()?->meta['path'] ?? null)->toBe('/www/wwwroot/druhy-web.cz'); // its own document root, not a name on the first site

    // the space of the plan is divided, never doubled: 50 GB = 30 for the first site, 20 for the new one
    expect(ServiceSites::share($second, $service))->toBe(20)
        ->and(ServiceSites::share($service->fresh(), $service->fresh()))->toBe(30)
        ->and(array_column(app(ServiceFeatures::class)->resources($service->fresh(), 'sites', true), 'domain'))->toBe(['shop.cz', 'druhy-web.cz']);
});

it('sells no more sites than the plan states', function () {
    $sites = [];
    $blob = gzencode(str_repeat('site files', 50));
    planSitesPanel($sites, $blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $sites[] = ['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1', 'ps' => 'onhost'];
    $service->forceFill(['entitlements' => array_replace((array) $service->entitlements, ['sites' => 2])])->save();

    app(ServiceService::class)->requestAction($service->fresh(), 'site.create', $this->contextFor($user, $org), 'site-2a', ['domain' => 'druhy.cz', 'nvme_gb' => 10]);
    driveOperations();
    expect(Service::query()->where('hostname', 'druhy.cz')->exists())->toBeTrue();

    expect(fn () => app(ServiceService::class)->requestAction($service->fresh(), 'site.create', $this->contextFor($user, $org), 'site-2b', ['domain' => 'treti.cz', 'nvme_gb' => 10]))
        ->toThrow(DomainError::class, 'Tarif nabízí 2');
});

it('never leaves a site less space than it already stores, and never takes a name that is taken', function () {
    $sites = [];
    $blob = gzencode(str_repeat('site files', 50));
    planSitesPanel($sites, $blob);
    // the node says the first site already stores 45 of the plan's 50 GB: a new site of 20 GB would leave it 30 and break it
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell([
        '/^stat -c %s/' => [0, (string) strlen($blob)],
        '/du -sb/' => [0, (string) (45 * 1024 ** 3)."\n1200\n"],
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $sites[] = ['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1', 'ps' => 'onhost'];

    expect(fn () => app(ServiceService::class)->requestAction($service->fresh(), 'site.create', $this->contextFor($user, $org), 'site-3', ['domain' => 'treti-web.cz', 'nvme_gb' => 20]))
        ->toThrow(DomainError::class, 'už uložil');

    // and a name another service of the platform already hosts is refused before anything is created
    expect(fn () => app(ServiceService::class)->requestAction($service->fresh(), 'site.create', $this->contextFor($user, $org), 'site-3b', ['domain' => 'shop.cz', 'nvme_gb' => 5]))
        ->toThrow(DomainError::class, 'už ve službě je');
});

it('takes a site away only through its own cancellation, and never somebody else\'s', function () {
    $sites = [];
    $blob = gzencode(str_repeat('site files', 50));
    planSitesPanel($sites, $blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $sites[] = ['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1', 'ps' => 'onhost'];
    app(ServiceService::class)->requestAction($service, 'site.create', $this->contextFor($user, $org), 'site-4a', ['domain' => 'odebrany.cz', 'nvme_gb' => 10]);
    driveOperations();
    $second = Service::query()->where('hostname', 'odebrany.cz')->firstOrFail();

    // the service the customer pays for is not a site anybody may remove from it
    expect(fn () => app(ServiceService::class)->requestAction($service->fresh(), 'site.delete', $this->contextFor($user, $org, 'webauthn'), 'site-4b', ['site_id' => $service->id]))
        ->toThrow(DomainError::class);

    $drop = app(ServiceService::class)->requestAction($service->fresh(), 'site.delete', $this->contextFor($user, $org, 'webauthn'), 'site-4c', ['site_id' => $second->id]);
    driveOperation($drop, 25);
    expect($drop->refresh()->state)->toBe(Operation::SUCCEEDED, $drop->state.': '.(string) data_get($drop->error, 'message', ''));

    $own = Operation::query()->where('service_id', $second->id)->where('desired->action', 'terminate')->first();
    expect($own)->not->toBeNull(); // its own cancellation: archived first, then the grace window, then the panel
    driveOperation($own, 25);
    expect($second->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and($second->fresh()->terminate_at)->not->toBeNull()
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
});
