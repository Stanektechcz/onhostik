<?php

declare(strict_types=1);

use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0027 C3: an event is routed by exactly one arm of the router's match. Four service events were listed twice — once
 * for the customer, once in the staff list — and PHP's match takes the first arm, so staff never heard of a failed restore
 * test, a failed database import or a backup schedule that stopped or stalled, although the events catalog promises
 * "customer + operator" for each.
 */

/** @return array<string, int> every event name the router's main match names, with how many arms name it */
function routerArmNames(): array
{
    $source = (string) file_get_contents(base_path('domains/Notifications/NotificationRouter.php'));
    $start = strpos($source, 'match ($m->name) {');
    expect($start)->not->toBeFalse();
    $end = strpos($source, "\n        };\n", (int) $start);
    preg_match_all("/^ {12}('[a-z0-9_.]+'(?:\\s*,\\s*'[a-z0-9_.]+')*)\\s*=>/m", substr($source, (int) $start, (int) $end - (int) $start), $arms);
    $seen = [];
    foreach ($arms[1] as $list) {
        preg_match_all("/'([a-z0-9_.]+)'/", $list, $names);
        foreach ($names[1] as $name) {
            $seen[$name] = ($seen[$name] ?? 0) + 1;
        }
    }

    return $seen;
}

it('routes every event through one arm of the router, so no audience is silently skipped', function () {
    $names = routerArmNames();

    expect(count($names))->toBeGreaterThan(100)
        ->and(array_keys(array_filter($names, fn (int $count) => $count > 1)))->toBe([]);
});

it('tells both the customer and staff about the service troubles the catalog promises to both', function (string $event, array $payload) {
    [, $org] = $this->customerWithOrganization();

    app(OutboxPublisher::class)->publish(GenericEvent::of($event, 'service', 'svc_router_arms', $payload, $org->id));
    app(OutboxPublisher::class)->relayPending();

    $staff = Notification::query()->where('audience', 'internal')->where('event', $event)->get();
    $customer = Notification::query()->where('audience', 'customer')->where('event', $event)->where('organization_id', $org->id)->get();
    expect($staff)->toHaveCount(1)->and($customer)->toHaveCount(1)
        ->and($staff->first()->surface)->toBe('/sprava/sluzby')->and($staff->first()->severity)->toBe('hot')->and($staff->first()->kind)->toBe('infra')
        ->and($customer->first()->surface)->toBe('/panel/sluzby')->and($customer->first()->severity)->toBe('warn');
})->with([
    'restore test failed' => ['service.restore_test.failed', ['label' => 'Web A', 'problem' => 'tabulky nesouhlasí']],
    'database import failed' => ['service.database.import.failed', ['label' => 'Web A', 'database' => 'db_a', 'restored' => true, 'reason' => 'syntax error']],
    'backup schedule paused' => ['service.backup.schedule.paused', ['label' => 'Web A', 'failures' => 5, 'reason' => 'disk full']],
    'backup schedule stalled' => ['service.backup.schedule.stalled', ['label' => 'Web A', 'missed' => 3, 'reason' => 'panel down']],
]);
