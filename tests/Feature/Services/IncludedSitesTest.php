<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\DestructivePreview;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\StagingLink;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * A web hosting service is not only the site it was ordered for: the platform puts further sites of the same customer
 * on the node underneath it and bills none of them separately — the test copy today, the plan's further sites next.
 * They are services of their own (`tags.billing = included`, `tags.parent_service_id`), and nothing ever ended one
 * together with the service it belongs to. The cancellation dialog even promises the customer it does
 * (`DestructivePreview`: „Odstraní se i testovací kopie webu.“): the site went on serving, its files and databases
 * went on living on the node after the customer had cancelled, and no archive of them was ever made.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

/** The panel of the cancellation: one site per binding, no databases, no cron, and a file archive that can be pulled. */
function includedSitesPanel(string $blob): void
{
    Http::fake(function ($request) use ($blob) {
        if (! str_contains($request->url(), 'managed01.mgmt.test')) {
            return null;
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);

        return match (true) {
            str_contains($q, 'table=sites') => Http::response(['data' => [
                ['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1', 'ps' => 'onhost'],
                ['id' => 42, 'name' => 'shop-staging.web.onhost.cz', 'path' => '/www/wwwroot/shop-staging.web.onhost.cz', 'status' => '1', 'ps' => 'onhost'],
            ], 'page' => '']),
            str_contains($q, 'GetFileBody') => Http::response(['status' => true, 'data' => base64_encode($blob)]),
            str_contains($q, 'GetDir') => Http::response(['PATH' => (string) ($request->data()['path'] ?? ''), 'DIR' => [], 'FILES' => ['index.php;120;1700000000;0644;www']]),
            default => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']),
        };
    });
}

/** The test copy of a site, exactly as `StagingService::createStaging` leaves it: an included service of its own. */
function includedStagingSite(Service $production): Service
{
    $domain = 'shop-staging.web.onhost.cz';
    $staging = Service::query()->create([
        'organization_id' => $production->organization_id, 'project_id' => $production->project_id, 'product_key' => $production->product_key, 'plan_version_id' => $production->plan_version_id,
        'family' => $production->family, 'name' => 'Staging · '.$production->name, 'label' => 'staging', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => $production->region_code,
        'entitlements' => array_replace((array) $production->entitlements, ['staging' => false, 'backups' => false]), 'sla_class' => $production->sla_class,
        'tags' => ['parent_service_id' => $production->id, 'staging_of' => $production->id, 'billing' => 'included'],
        'desired_spec' => ['executor' => 'aapanel', 'family' => 'web', 'domain' => $domain, 'php_version' => '8.3', 'staging_of' => $production->id],
        'hostname' => $domain, 'node_id' => $production->node_id, 'provider_instance_id' => $production->provider_instance_id, 'activated_at' => now(), 'health' => [],
    ]);
    ProviderBinding::query()->create([
        'service_id' => $staging->id, 'provider_instance_id' => $production->provider_instance_id, 'remote_type' => 'site', 'remote_id' => '42', 'remote_node' => 'aapanel-managed01',
        'meta' => ['name' => $domain, 'path' => '/www/wwwroot/'.$domain], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'included-staging:'.$staging->id, 'adapter_version' => '1.0.0',
    ]);
    StagingLink::query()->create(['service_id' => $production->id, 'staging_service_id' => $staging->id, 'organization_id' => $production->organization_id, 'staging_domain' => $domain, 'state' => 'ready', 'databases' => [], 'meta' => []]);

    return $staging->refresh();
}

it('ends the test copy of the site it promised to end when the web hosting is cancelled', function () {
    $blob = gzencode(str_repeat('site files', 50));
    includedSitesPanel($blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [$user, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $staging = includedStagingSite($production);

    // the dialog the customer confirms says the test copy goes with it
    expect(app(DestructivePreview::class)->of($production, 'terminate')['depends'])
        ->toContain('Odstraní se i testovací kopie webu.');

    $cancel = driveOperation(app(ServiceService::class)->requestAction($production, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'incl-term-1', ['reason' => 'customer request']), 25);
    expect($cancel->state)->toBe(Operation::SUCCEEDED, $cancel->state.': '.(string) data_get($cancel->error, 'message', ''));

    // it is a site of the customer like any other: it is archived and then it goes, it does not keep serving for ever
    $own = Operation::query()->where('service_id', $staging->id)->where('desired->action', 'terminate')->first();
    expect($own)->not->toBeNull();
    driveOperation($own, 25);
    expect($staging->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and($staging->fresh()->terminate_at)->not->toBeNull()
        ->and(StagingLink::query()->where('service_id', $production->id)->exists())->toBeFalse();
});

it('does not keep serving from the test copy while the web hosting is suspended', function () {
    $blob = gzencode(str_repeat('site files', 50));
    includedSitesPanel($blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $staging = includedStagingSite($production);

    driveOperation(app(ServiceService::class)->requestAction($production, 'suspend', CommandContext::system('dunning'), 'incl-susp-1', ['reason' => 'nezaplaceno']), 25);
    expect(Operation::query()->where('service_id', $staging->id)->where('desired->action', 'suspend')->exists())->toBeTrue()
        ->and($staging->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and(data_get($staging->fresh()->tags, 'included.held_by'))->toBe($production->id);

    // and it comes back with the service it belongs to
    driveOperation(app(ServiceService::class)->requestAction($production->fresh(), 'resume', CommandContext::system('paid'), 'incl-res-1', ['reason' => 'zaplaceno', 'lift' => 'review']), 25);
    expect($staging->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(data_get($staging->fresh()->tags, 'included.held_by'))->toBeNull();
});

it('leaves a test copy the customer suspended themselves suspended when the service comes back', function () {
    $blob = gzencode(str_repeat('site files', 50));
    includedSitesPanel($blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $staging = includedStagingSite($production);
    $staging->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save(); // switched off before the service was
    $production->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();

    driveOperation(app(ServiceService::class)->requestAction($production->fresh(), 'resume', CommandContext::system('paid'), 'incl-res-2', ['reason' => 'zaplaceno']), 25);

    expect($staging->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and(Operation::query()->where('service_id', $staging->id)->where('desired->action', 'resume')->exists())->toBeFalse();
});

it('leaves a service of another customer alone, whatever it says about its parent', function () {
    $blob = gzencode(str_repeat('site files', 50));
    includedSitesPanel($blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [$user, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    [, $other] = $this->customerWithOrganization();
    $stranger = Service::query()->create([
        'organization_id' => $other->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Cizí web', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => $production->region_code,
        'provider_instance_id' => $production->provider_instance_id, 'entitlements' => [], 'sla_class' => 'standard', 'activated_at' => now(),
        'tags' => ['parent_service_id' => $production->id, 'billing' => 'included'], 'desired_spec' => ['executor' => 'aapanel', 'domain' => 'cizi.cz'], 'hostname' => 'cizi.cz',
    ]);

    driveOperation(app(ServiceService::class)->requestAction($production, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'incl-term-2', ['reason' => 'customer request']), 25);

    expect($stranger->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(Operation::query()->where('service_id', $stranger->id)->exists())->toBeFalse();
});

it('does not end a service somebody pays for separately', function () {
    $blob = gzencode(str_repeat('site files', 50));
    includedSitesPanel($blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [$user, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $paid = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Druhý web', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => $production->region_code,
        'provider_instance_id' => $production->provider_instance_id, 'entitlements' => [], 'sla_class' => 'standard', 'activated_at' => now(),
        'tags' => ['parent_service_id' => $production->id], 'desired_spec' => ['executor' => 'aapanel', 'domain' => 'placeny.cz'], 'hostname' => 'placeny.cz', // no billing: included — it has an invoice of its own
    ]);

    driveOperation(app(ServiceService::class)->requestAction($production, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'incl-term-3', ['reason' => 'customer request']), 25);

    expect($paid->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(Operation::query()->where('service_id', $paid->id)->exists())->toBeFalse();
});

/*
 * Undoing the cancellation has to reach them too.
 *
 * A cancellation may be taken back for the whole grace window — a plain `resume` does it, and `cancellationCleared`
 * nulls `terminate_at`. But it nulls it on the service it was called on, and nothing else: the carried sites were
 * ended by `endIncludedServicesStep` with `terminate_at` of their own and no `included.held_by`, so the resume step
 * skipped every one of them (its condition wants `held_by` set AND `terminate_at` null — both false here).
 *
 * The customer's hosting then runs, paid and live, while `onhost:services:purge` takes each carried site on the day
 * its thirty days are up: files, databases and mailboxes deleted at the panel. On a "10 webů" plan that is nine
 * websites destroyed after the customer already changed their mind — and they cannot rescue one by hand either, the
 * name is still held by the site that is about to be purged.
 */

it('brings back the sites it carried when the customer undoes the cancellation', function () {
    $blob = gzencode(str_repeat('site files', 50));
    includedSitesPanel($blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [$user, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $staging = includedStagingSite($production);

    driveOperation(app(ServiceService::class)->requestAction($production, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'incl-undo-1', ['reason' => 'customer request']), 25);
    $own = Operation::query()->where('service_id', $staging->id)->where('desired->action', 'terminate')->first();
    driveOperation($own, 25);
    expect($staging->fresh()->terminate_at)->not->toBeNull(); // it is on its way out with the parent

    // two days later the customer changes their mind: a plain resume takes the cancellation back
    driveOperation(app(ServiceService::class)->requestAction($production->fresh(), 'resume', $this->contextFor($user, $org), 'incl-undo-2', ['reason' => 'přeci jen pokračujeme']), 25);
    foreach (Operation::query()->where('service_id', $staging->id)->where('desired->action', 'resume')->get() as $operation) {
        driveOperation($operation, 25);
    }

    expect($production->fresh()->terminate_at)->toBeNull()
        ->and($staging->fresh()->terminate_at)->toBeNull()                       // …and so is the site it carries
        ->and($staging->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
});

it('leaves a site the customer had already cancelled on its own way out', function () {
    $blob = gzencode(str_repeat('site files', 50));
    includedSitesPanel($blob);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^stat -c %s/' => [0, (string) strlen($blob)]]);
    [$user, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $staging = includedStagingSite($production);
    // the customer cancelled the test copy themselves, before they cancelled the hosting
    $staging->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'terminate_at' => now()->addDays(30), 'tags' => array_merge((array) $staging->tags, ['deletion' => ['reason' => 'zrušil zákazník']])])->save();

    driveOperation(app(ServiceService::class)->requestAction($production, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'incl-undo-3', ['reason' => 'customer request']), 25);
    driveOperation(app(ServiceService::class)->requestAction($production->fresh(), 'resume', $this->contextFor($user, $org), 'incl-undo-4', ['reason' => 'přeci jen pokračujeme']), 25);

    expect($staging->fresh()->terminate_at)->not->toBeNull()   // not ours to undo: the customer ended this one
        ->and($staging->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED);
});
