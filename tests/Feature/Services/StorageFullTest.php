<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\UsageGuard;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * What happens when a plan is used up. The platform measured it — 85 % tells the customer, 95 % may order the next
 * plan — and then did nothing at all at 100 %. On ISPConfig the node stops a full site writing (its quota is a
 * filesystem quota) and the customer sees a site that cannot save, with no word from us; on aaPanel there is no quota
 * at all, so one site can fill the node's disk and stop every other customer on it.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

/** The measurement `UsageWatch` leaves on the service. */
function measured(Service $service, int $pct, string $when = 'now', string $key = 'disk'): Service
{
    $limit = 50 * 1024 ** 3;
    $service->forceFill(['tags' => array_merge((array) $service->tags, ['usage' => [
        'level' => $pct >= 100 ? 'full' : 'ok', 'checked_at' => $when === 'now' ? now()->toIso8601String() : now()->subHours(72)->toIso8601String(),
        'metrics' => [$key => ['used' => (int) ($limit * $pct / 100), 'limit' => $limit, 'pct' => $pct]],
    ]])])->save();

    return $service->refresh();
}

it('does not let a service with no room left store more, and leaves the ways out open', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = measured(featureWebService($org, 'aapanel'), 103);
    $services = app(ServiceService::class);
    $ctx = $this->contextFor($user, $org);

    foreach (['file.save' => ['path' => 'a.txt', 'content' => 'x'], 'database.create' => ['name' => 'nova'], 'app.install' => ['name' => 'wordpress']] as $action => $params) {
        expect(fn () => $services->requestAction($service, $action, $ctx, 'full-'.$action, $params))
            ->toThrow(DomainError::class, 'zaplněno');
    }

    // making room and protecting the data are the ways out: they are never refused for being full
    expect(UsageGuard::full($service))->not->toBeNull();
    foreach (['file.delete', 'database.delete', 'backup', 'terminate'] as $action) {
        expect(in_array($action, UsageGuard::GROWING, true))->toBeFalse();
    }
});

it('does not hold a service back on a measurement nobody refreshed', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = measured(featureWebService($org, 'aapanel'), 120, 'stale');

    expect(UsageGuard::full($service))->toBeNull(); // three days old: the site may have been emptied since
    expect(fn () => app(ServiceService::class)->requestAction($service, 'file.save', $this->contextFor($user, $org), 'stale-1', ['path' => 'a.txt', 'content' => 'x']))
        ->not->toThrow(DomainError::class, 'zaplněno');
});

it('tells the customer the plan is used up, once, and above the warning', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    // the node says the site stores 55 of the plan's 50 GB
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/du -sb/' => [0, (string) (55 * 1024 ** 3)."\n1200\n"]]);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));

    $stats = app(UsageWatch::class)->run();
    expect($stats['full'])->toBe(1)
        ->and(data_get($service->fresh()->tags, 'usage.level'))->toBe('full')
        ->and(data_get($service->fresh()->tags, 'usage.metrics.disk.pct'))->toBeGreaterThanOrEqual(100);

    $published = OutboxMessage::query()->where('name', 'service.usage.high')->get();
    expect($published)->toHaveCount(1)->and(data_get($published->first()->payload, 'level'))->toBe('full');

    // the same measurement on the same day is not sent again
    app(UsageWatch::class)->run();
    expect(OutboxMessage::query()->where('name', 'service.usage.high')->count())->toBe(1);
});

it('climbs the ladder: a service that was only warned about hears again when it fills up', function () {
    expect(UsageWatch::rank('ok'))->toBeLessThan(UsageWatch::rank('warn'))
        ->and(UsageWatch::rank('warn'))->toBeLessThan(UsageWatch::rank('critical'))
        ->and(UsageWatch::rank('critical'))->toBeLessThan(UsageWatch::rank('full'))
        ->and(UsageWatch::level(['disk' => ['used' => 1, 'limit' => 1, 'pct' => 100]]))->toBe('full')
        ->and(UsageWatch::level(['disk' => ['used' => 1, 'limit' => 1, 'pct' => 99]]))->toBe('critical');
});
