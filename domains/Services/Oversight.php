<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Carbon\CarbonImmutable;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Web\VerifiedRestore;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * W2-5 "Dohled" (oversight): a paid add-on of a web hosting. What it changes on the parent is written by `Addons`
 * (entitlement `oversight`, the uptime monitor it had to make, taken back on cancellation); what it does afterwards is here:
 * every service that carries it is looked at once an hour (`onhost:services:oversight`) with the same health check the
 * customer sees, and a finding that turns `bad` is
 *
 *  • told to the customer once, through the notification router (`service.oversight.finding_bad`), and
 *  • raised to the staff on-call as ONE alert per service and finding (`OnCallService::open`, dedup key service:finding) —
 *    the same finding seen again while it is still bad only counts one more occurrence on that alert, nobody is paged twice.
 *
 * It reports what it saw and when; it promises neither that nothing will happen nor that a finding it does not report is
 * absent. A finding that is not current (old, never measured, collector failed) is not "bad" here — it is not known.
 */
final class Oversight
{
    public const ADDON = 'oversight';

    /** The key on the parent's entitlements the add-on sets. */
    public const ENTITLEMENT = 'oversight';

    public const EVENT = 'service.oversight.finding_bad';

    public function __construct(private readonly ServiceHealthCheck $health, private readonly OnCallService $onCall, private readonly OutboxPublisher $outbox) {}

    /** A web hosting of its own — the monitor and the checks are about the site, never a staging site, a carried site or a restore copy. */
    public static function eligible(Service $service): bool
    {
        return VerifiedRestore::eligible($service);
    }

    public static function active(Service $service): bool
    {
        return (((array) $service->entitlements)[self::ENTITLEMENT] ?? null) === true;
    }

    /** @return array<string,array{since:string, last:string}> findings of the service that are bad now, by key, as the last look left them */
    public static function open(Service $service): array
    {
        return (array) data_get($service->tags, 'oversight.bad', []);
    }

    /** When the hourly look last ran for this service (null: never). */
    public static function lastLook(Service $service): ?CarbonImmutable
    {
        $at = data_get($service->tags, 'oversight.checked_at');

        return is_string($at) && $at !== '' ? CarbonImmutable::parse($at) : null;
    }

    /**
     * Looks at every service that carries the add-on.
     *
     * @return array{checked:int, opened:int, repeated:int, recovered:int}
     */
    public function sweep(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'opened' => 0, 'repeated' => 0, 'recovered' => 0];
        $parents = Service::query()->where('family', 'addon')->where('product_key', self::ADDON)->where('state', ServiceStateMachine::ACTIVE)
            ->orderBy('created_at')->limit($limit)->pluck('tags')->map(fn ($tags) => (string) data_get($tags, 'parent_service_id', ''))->filter()->unique();
        foreach (Service::query()->whereIn('id', $parents)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED])->get() as $service) {
            if (! self::active($service)) {
                continue;
            }
            $stats['checked']++;
            foreach ($this->watch($service) as $key => $count) {
                $stats[$key] += $count;
            }
        }

        return $stats;
    }

    /** @return array{opened:int, repeated:int, recovered:int} */
    public function watch(Service $service): array
    {
        $check = $this->health->run($service);
        $now = now()->toIso8601String();
        $before = self::open($service);
        $bad = [];
        $out = ['opened' => 0, 'repeated' => 0, 'recovered' => 0];
        foreach ((array) $check['findings'] as $finding) {
            // a reading that is old or missing is not a bad finding, it is an unknown one — and an unknown one is not an alarm
            if (($finding['level'] ?? null) !== 'bad' || (isset($finding['state']) && $finding['state'] !== Freshness::CURRENT)) {
                continue;
            }
            $key = (string) $finding['key'];
            $label = (string) $check['name'];
            $title = "Dohled: {$label} — ".$finding['cs'];
            $this->onCall->open(self::EVENT, 'service', self::subject($service, $key), mb_substr($title, 0, 250), (string) $finding['cs'], '/sprava/sluzby', 'warn'); // a repeat counts, it does not page again
            if (isset($before[$key])) {
                $bad[$key] = ['since' => (string) $before[$key]['since'], 'last' => $now];
                $out['repeated']++;

                continue;
            }
            $bad[$key] = ['since' => $now, 'last' => $now];
            $out['opened']++;
            $this->outbox->publish(GenericEvent::of(self::EVENT, 'service', $service->id, ['label' => $label, 'finding' => $key, 'problem' => (string) $finding['cs'], 'problem_en' => (string) $finding['en'], 'observed_at' => $finding['observed_at'] ?? null], $service->organization_id));
        }
        foreach (array_diff_key($before, $bad) as $key => $_) { // it is not bad any more: the alert of that finding closes
            $this->onCall->resolveByDedup(OnCallService::dedupKey(self::EVENT, 'service', self::subject($service, (string) $key)), 'oversight:recovered');
            $out['recovered']++;
        }
        $tags = (array) $service->tags;
        $tags['oversight'] = ['bad' => $bad, 'checked_at' => $now];
        $service->forceFill(['tags' => $tags])->save();

        return $out;
    }

    /** The add-on ended: what it still has open is closed, and so is its memory of it. */
    public function release(Service $service): void
    {
        foreach (array_keys(self::open($service)) as $key) {
            $this->onCall->resolveByDedup(OnCallService::dedupKey(self::EVENT, 'service', self::subject($service, (string) $key)), 'oversight:ended');
        }
        $tags = (array) $service->tags;
        unset($tags['oversight']);
        $service->forceFill(['tags' => $tags])->save();
    }

    /** One alert per service AND finding. */
    private static function subject(Service $service, string $key): string
    {
        return $service->id.':'.$key;
    }
}
