<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Notifications\DigestService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\Services\Oversight;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * W2-5 "Dohled" (oversight): a paid add-on of a web hosting. Buying it changes the parent (entitlement `oversight`, an uptime monitor if there
 * is none — and only that one is taken back when the add-on ends); having it means the monthly report prints the controls themselves and a
 * finding that turns bad is told to the customer once and raised to the on-call as one alert per service and finding.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Date::setTestNow('2026-10-12 09:00:00');
});

afterEach(fn () => Date::setTestNow());

/** The add-on bought for a web service, delivered through the Addons registry (as an order's line is). */
function ovAddon(Service $parent): Service
{
    $addon = Service::query()->create([
        'organization_id' => $parent->organization_id, 'product_key' => Oversight::ADDON, 'family' => 'addon', 'name' => 'Dohled', 'state' => ServiceStateMachine::ACTIVE, 'activated_at' => now(),
        'region_code' => $parent->region_code, 'provider_instance_id' => $parent->provider_instance_id, 'node_id' => $parent->node_id, 'entitlements' => ['oversight' => true],
        'sla_class' => $parent->sla_class, 'tags' => ['parent_service_id' => $parent->id], 'desired_spec' => ['parent_service_id' => $parent->id, 'addon' => Oversight::ADDON],
    ]);
    app(Addons::class)->apply($parent->fresh(), $addon);

    return $addon->fresh();
}

it('turns on monitoring and the entitlement when bought, and takes back the monitor it made when cancelled', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    expect(UptimeMonitor::query()->where('service_id', $service->id)->count())->toBe(0);

    $addon = ovAddon($service);

    $monitor = UptimeMonitor::query()->where('service_id', $service->id)->sole();
    expect($service->fresh()->entitlements['oversight'])->toBeTrue()
        ->and($monitor->url)->toBe('https://shop.cz/')->and($monitor->enabled)->toBeTrue()
        ->and(data_get($addon->tags, 'addon.patch'))->toBe(['oversight' => true])
        ->and(data_get($addon->tags, 'addon.monitor_id'))->toBe($monitor->id);

    $result = app(Addons::class)->revoke($service->fresh(), $addon);

    expect($result['restored'])->toBe(['oversight' => null])
        ->and($service->fresh()->entitlements)->not->toHaveKey('oversight')
        ->and(UptimeMonitor::query()->where('service_id', $service->id)->count())->toBe(0)
        ->and(data_get($addon->fresh()->tags, 'addon.monitor_removed'))->toBeTrue();
});

it('uses the monitor the service already has, makes no second one and leaves it alone on cancellation', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $own = UptimeMonitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://shop.cz/akce', 'interval_seconds' => 120, 'expected_status' => 200,
        'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up', 'consecutive_failures' => 0]);

    $addon = ovAddon($service);

    expect(UptimeMonitor::query()->where('service_id', $service->id)->count())->toBe(1)
        ->and(data_get($addon->tags, 'addon.monitor_id'))->toBeNull()
        ->and(data_get($addon->tags, 'addon.monitor_reused'))->toBe($own->id);

    app(Addons::class)->revoke($service->fresh(), $addon);

    $kept = UptimeMonitor::query()->where('service_id', $service->id)->sole();
    expect($kept->id)->toBe($own->id)->and($kept->url)->toBe('https://shop.cz/akce')->and($kept->interval_seconds)->toBe(120)
        ->and(data_get($addon->fresh()->tags, 'addon.monitor_removed'))->toBeFalse();
});

it('refuses the add-on for a site that is not a web hosting of its own', function () {
    [, $org] = $this->customerWithOrganization();
    $staging = featureWebService($org, 'aapanel');
    $staging->forceFill(['tags' => ['staging_of' => 'svc_other']])->save();
    $addon = new Service(['product_key' => Oversight::ADDON, 'entitlements' => ['oversight' => true], 'tags' => []]);

    expect(Addons::sellable(Oversight::ADDON))->toBeTrue()
        ->and(fn () => app(Addons::class)->apply($staging, $addon))->toThrow(DomainError::class);
    expect(UptimeMonitor::query()->where('service_id', $staging->id)->count())->toBe(0);
});

it('raises a finding that turned bad once: one customer notice, one alert, repeats only counted', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    ovAddon($service);
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save(); // "Služba je pozastavená." is a bad finding of a current record

    $first = app(Oversight::class)->sweep();
    expect($first)->toMatchArray(['checked' => 1, 'opened' => 1, 'repeated' => 0, 'recovered' => 0]);
    app(OutboxPublisher::class)->relayPending();

    $alert = OnCallAlert::query()->where('event', Oversight::EVENT)->sole();
    expect($alert->dedup_key)->toBe(Oversight::EVENT.':service:'.$service->id.':state')->and($alert->occurrences)->toBe(1)->and($alert->state)->toBe(OnCallAlert::OPEN);
    $told = Notification::query()->where('event', Oversight::EVENT)->get();
    expect($told->where('audience', 'customer')->count())->toBe(1)->and($told->where('audience', 'internal')->count())->toBe(1);

    // the same finding on the next looks: the alert counts, nobody is told again
    Date::setTestNow(now()->addHour());
    expect(app(Oversight::class)->sweep())->toMatchArray(['opened' => 0, 'repeated' => 1]);
    Date::setTestNow(now()->addHour());
    app(Oversight::class)->sweep();
    app(OutboxPublisher::class)->relayPending();

    expect(OnCallAlert::query()->where('event', Oversight::EVENT)->count())->toBe(1)
        ->and(OnCallAlert::query()->where('event', Oversight::EVENT)->sole()->occurrences)->toBe(3)
        ->and(Notification::query()->where('event', Oversight::EVENT)->where('audience', 'customer')->count())->toBe(1);

    // it recovers: the alert closes; when it turns bad again it is a new thing to tell
    $service->forceFill(['state' => ServiceStateMachine::ACTIVE])->save();
    expect(app(Oversight::class)->sweep())->toMatchArray(['recovered' => 1, 'opened' => 0]);
    expect(OnCallAlert::query()->where('event', Oversight::EVENT)->sole()->state)->toBe(OnCallAlert::RESOLVED)->and(Oversight::open($service->fresh()))->toBe([]);
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();
    expect(app(Oversight::class)->sweep()['opened'])->toBe(1);
    app(OutboxPublisher::class)->relayPending();
    expect(OnCallAlert::query()->where('event', Oversight::EVENT)->count())->toBe(2)
        ->and(Notification::query()->where('event', Oversight::EVENT)->where('audience', 'customer')->count())->toBe(2);
});

it('watches only services that carry the add-on, and not a finding whose reading is old', function () {
    [, $org] = $this->customerWithOrganization();
    $without = featureWebService($org, 'aapanel');
    $without->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();

    expect(app(Oversight::class)->sweep()['checked'])->toBe(0)
        ->and(OnCallAlert::query()->where('event', Oversight::EVENT)->count())->toBe(0);
});

it('prints the controls themselves in the monthly report of a service with the add-on, and nothing more for one without', function () {
    [, $org] = $this->customerWithOrganization();
    $with = featureWebService($org, 'aapanel');
    ovAddon($with);
    app(Oversight::class)->sweep();

    $lines = app(DigestService::class)->customerMonthly($org)['sections'][(string) ($with->hostname ?: ($with->label ?: $with->name))];
    $detail = array_values(array_filter($lines, fn (string $l) => str_starts_with($l, 'Dohled · ')));
    expect($detail)->not->toBeEmpty()
        ->and(implode("\n", $detail))->toContain('Dohled · neověřeno:')->toContain('žádné zjištění nebylo při posledním pohledu ve špatném stavu')->toContain('ověřeno 12. 10. 2026');

    $with->forceFill(['state' => ServiceStateMachine::ACTIVE])->save();
    $without = $with->replicate(['hostname', 'name_prefix'])->fill(['hostname' => 'druhy.cz', 'tags' => [], 'entitlements' => ['sites' => 1]]);
    $without->save();
    $report = app(DigestService::class)->customerMonthly($org)['sections'];
    expect(collect($report['druhy.cz'])->filter(fn (string $l) => str_starts_with($l, 'Dohled'))->all())->toBe([]);
});

it('does not send the report to an organization that switched the digests off, add-on or not', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    ovAddon($service);
    $org->forceFill(['settings' => array_merge((array) $org->settings, ['digest' => ['frequency' => 'off']])])->save();

    expect(app(DigestService::class)->monthly()['sent'])->toBe(0)
        ->and(Notification::query()->where('event', 'digest.monthly')->count())->toBe(0);
});

it('words everything it says without a guarantee or a claim of safety', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    ovAddon($service);
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();
    app(Oversight::class)->sweep();
    app(OutboxPublisher::class)->relayPending();

    $texts = Notification::query()->where('event', Oversight::EVENT)->get()->map(fn ($n) => $n->title.' '.$n->body)->implode(' ');
    $texts .= ' '.OnCallAlert::query()->where('event', Oversight::EVENT)->get()->map(fn ($a) => $a->title.' '.$a->body)->implode(' ');
    $service->forceFill(['state' => ServiceStateMachine::ACTIVE])->save(); // the report describes active services; the open finding is remembered
    $texts .= ' '.implode(' ', app(DigestService::class)->customerMonthly($org)['sections'][(string) ($service->hostname ?: $service->name)]);
    $definition = CatalogRevisions::PRODUCTS[Oversight::ADDON];
    $texts .= ' '.json_encode($definition['description'], JSON_UNESCAPED_UNICODE).' '.json_encode($definition['plans'], JSON_UNESCAPED_UNICODE);

    expect($texts)->not->toMatch('/garanc|garant|zaručen|bezpečné|bezpečný|guarantee|\bsecure\b|\bsafe\b/iu');
});

it('defines the product as a draft without a price, for staff to price', function () {
    $definition = CatalogRevisions::PRODUCTS[Oversight::ADDON];

    expect($definition['state'])->toBe('draft')->and($definition['meta']['admin_priced'])->toBeTrue()
        ->and(array_column($definition['plans'], 'entitlements'))->toBe([['oversight' => true]])
        ->and(CatalogRevisions::REVISIONS['2026-10-oversight']['create'])->toBe([Oversight::ADDON]);
    foreach ($definition['plans'] as $plan) {
        expect($plan)->not->toHaveKey('prices');
    }
});
