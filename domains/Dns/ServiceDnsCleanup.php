<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns;

use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Services\Mail\MailDomains;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Throwable;

/**
 * What a service published into DNS goes when the service does.
 *
 * A hosting is written into DNS in three places, each by a publisher with a name of its own: the site's A/AAAA in the
 * customer's own zone (`web:<service>`), the records the pairing of a further domain added (`service:<service>`), and
 * the mail records of every mail domain the service was given (`mail:<domain>` — MX, SPF, DMARC, DKIM, autoconfig).
 * The removal took none of them. The customer's domain went on resolving to a node that no longer served their site —
 * where a visitor meets whatever that node answers for a name it does not know, which on a shared node is another
 * customer's website — and their mail kept being sent to a node that no longer accepts it, with SPF and DKIM still
 * authorising it.
 *
 * Only what this service published is removed. A record the customer made themselves is `managed_by = customer` and
 * `syncSystemRecords` never touches it, and a record of another publisher keeps its own owner. The domain is left
 * pointing nowhere rather than at a parking page: the platform stops asserting where a domain lives once it hosts
 * nothing of it, and parking is an action of its own (`domains.use_onhost_dns`, template `parking`).
 *
 * A zone that cannot be published to does not hold up a termination — the customer is entitled to be rid of the
 * service — but it comes back in `failed`, and the caller says so out loud.
 */
final class ServiceDnsCleanup
{
    public function __construct(private readonly DnsService $dns) {}

    /**
     * @return array{zones: list<string>, removed: list<string>, failed: list<string>}
     */
    public function run(Service $service, CommandContext $actor, string $reason): array
    {
        $out = ['zones' => [], 'removed' => [], 'failed' => []];
        $this->platformHostname($service, $actor, $reason, $out);
        $owners = array_values(array_unique(array_merge(
            ['web:'.$service->id, 'service:'.$service->id],
            array_map(fn (string $domain) => 'mail:'.$domain, self::mailDomains($service)),
        )));
        foreach (self::zonesOf($service) as $zone) {
            $out['zones'][] = $zone->name;
            foreach ($owners as $owner) {
                try {
                    if ($this->dns->syncSystemRecords($zone, [], $actor, $reason, $owner) !== null) {
                        $out['removed'][] = $zone->name.' ('.$owner.')';
                    }
                } catch (Throwable $e) {
                    $out['failed'][] = $zone->name.' ('.$owner.')';
                }
            }
        }

        return $out;
    }

    /**
     * The hosting hostname in a platform zone (`<label>.web.onhost.cz`) loses its A/AAAA rows with the service.
     *
     * @param  array{zones: list<string>, removed: list<string>, failed: list<string>}  $out
     */
    private function platformHostname(Service $service, CommandContext $actor, string $reason, array &$out): void
    {
        $platform = $this->dns->platformZoneFor((string) $service->hostname);
        if ($platform === null) {
            return;
        }
        [$zone, $relative] = $platform;
        $out['zones'][] = $zone->name;
        try {
            if ($this->dns->syncHostname($zone, $relative, null, null, $actor, "service:{$service->id}", $reason) !== null) {
                $out['removed'][] = $zone->name.' ('.$relative.')';
            }
        } catch (Throwable $e) {
            $out['failed'][] = $zone->name.' ('.$relative.')';
        }
    }

    /**
     * Every mail domain the service was given — from its bindings, and from the `mail_domains` rows, because the
     * step that removes the mail domain deletes the binding before this one runs.
     *
     * @return list<string>
     */
    private static function mailDomains(Service $service): array
    {
        $names = MailDomains::bindingsOf($service)->map(fn ($binding) => MailDomains::nameOf($binding))->all();
        foreach (MailDomain::query()->where('service_id', $service->id)->pluck('domain') as $domain) {
            $names[] = mb_strtolower(trim((string) $domain));
        }

        return array_values(array_unique(array_filter($names, fn (string $name) => $name !== '')));
    }

    /**
     * The organization's own zones that hold any of the service's names — the site's domain, the names it also
     * answered for, and its mail domains. A name is looked for in the zone that covers it, so a site on
     * `blog.shop.cz` finds the zone `shop.cz`.
     *
     * @return list<DnsZone>
     */
    private static function zonesOf(Service $service): array
    {
        $names = array_merge(
            [(string) $service->hostname, (string) $service->spec('domain', '')],
            array_map('strval', (array) $service->spec('aliases', [])),
            array_map('strval', (array) $service->spec('extra_domains', [])),
            self::mailDomains($service),
        );
        $zones = [];
        foreach (array_unique(array_filter(array_map(fn (string $name) => mb_strtolower(trim($name, ". \t\n")), $names))) as $name) {
            $labels = explode('.', $name);
            while (count($labels) >= 2) {
                $zone = DnsZone::query()->where('name', implode('.', $labels))->where('organization_id', $service->organization_id)->where('state', 'active')->first();
                if ($zone !== null) {
                    $zones[$zone->id] = $zone;
                    break;
                }
                array_shift($labels);
            }
        }

        return array_values($zones);
    }
}
