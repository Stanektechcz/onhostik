<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Dns\DomainPointing;
use Onhost\Domain\Services\Models\CdnZone;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Dns\RecordResolver;
use Throwable;

/**
 * A site behind the CDN is only protected while nobody can find the node behind it.
 *
 * Proxying the main name hides the origin only as long as every other public name does not hand the address out:
 * `direct.`, `origin.`, `ftp.`, `mail.` and the host of the domain's MX are the classic leaks (a mail server on the same
 * machine answers on the very address the CDN is there to hide), and so is the main name itself when its record has
 * fallen out of the proxy. The attacker asks public DNS, gets the address and walks round the WAF.
 *
 * This asks public DNS (the same read-only resolver the other DNS checks use — no connection to anything is made) and
 * records which names answer with an address of the node. Advice only: whether `mail.` may stay is the customer's call.
 */
final class OriginExposureWatch
{
    /** Names under the apex that commonly carry the origin address. */
    public const LEAK_LABELS = ['direct', 'origin', 'mail', 'ftp'];

    public function __construct(private readonly DomainPointing $pointing, private readonly RecordResolver $dns, private readonly CdnService $cdn) {}

    /** @return array{checked:int, leaking:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'leaking' => 0, 'errors' => 0];
        $behind = CdnZone::query()->where('state', 'active')->pluck('service_id')->all();
        if ($behind === []) {
            return $stats;
        }
        $services = Service::query()
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereIn('family', SiteNames::WEB)->whereIn('id', $behind)->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($services as $service) {
            $origin = DomainPointing::addressesOf($service);
            $name = mb_strtolower(rtrim(trim((string) ($service->hostname ?? '')), '.'));
            if ($origin === [] || $name === '') {
                continue; // without the node's address there is nothing to compare against; no verdict is invented
            }
            try {
                $leaks = $this->leaks($service, $name, $origin);
            } catch (Throwable) {
                $stats['errors']++;

                continue;
            }
            $tags = (array) ($service->tags ?? []);
            $tags['origin_exposure'] = ['leaks' => $leaks, 'checked_at' => now()->toIso8601String()];
            $service->forceFill(['tags' => $tags])->save();
            $stats['checked']++;
            $stats['leaking'] += $leaks === [] ? 0 : 1;
        }

        return $stats;
    }

    /**
     * @param  list<string>  $origin  the node's own addresses
     * @return list<array{name:string, addresses:list<string>}> every name that answers with one of them
     */
    public function leaks(Service $service, string $name, array $origin): array
    {
        [$apex] = $this->cdn->apexOf($service, $name);
        $names = [$name, $apex];
        foreach (self::LEAK_LABELS as $label) {
            $names[] = $label.'.'.$apex;
        }
        foreach ($this->dns->records($apex, 'MX') as $mx) {
            if (($target = rtrim(mb_strtolower((string) ($mx['target'] ?? '')), '.')) !== '') {
                $names[] = $target;
            }
        }
        $leaks = [];
        foreach (array_values(array_unique($names)) as $candidate) {
            $hit = array_values(array_intersect($this->pointing->answers($candidate), $origin));
            if ($hit !== []) {
                $leaks[] = ['name' => $candidate, 'addresses' => $hit];
            }
        }

        return $leaks;
    }
}
