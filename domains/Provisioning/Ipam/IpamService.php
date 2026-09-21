<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Ipam;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\IpPool;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Support\Hostname;
use Throwable;

/**
 * IPv4/IPv6 allocation with row locks. IPv4 is a scarce, billable resource
 * (blueprint §13, §49.5): pools keep an emergency reserve and exhaustion emits an
 * alert instead of silently failing.
 */
final class IpamService
{
    public function __construct(private readonly OutboxPublisher $outbox) {}

    /** Allocate the next free address of a family in a region/purpose; idempotent per service+family. */
    public function allocate(int $family, string $regionCode, string $purpose, string $serviceId, string $organizationId): IpAddress
    {
        $existing = IpAddress::query()->where('service_id', $serviceId)->where('family', $family)->where('state', 'allocated')->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($family, $regionCode, $purpose, $serviceId, $organizationId) {
            $pools = IpPool::query()->where('family', $family)->where('region_code', $regionCode)->where('purpose', $purpose)->where('state', 'active')->get();
            foreach ($pools as $pool) {
                $free = IpAddress::query()->where('pool_id', $pool->id)->where('state', 'free')->count();
                if ($free <= $pool->reserve_count) {
                    continue;
                }
                $address = IpAddress::query()->where('pool_id', $pool->id)->where('state', 'free')->orderBy('address')->lockForUpdate()->first();
                if ($address === null) {
                    continue;
                }
                $address->forceFill(['state' => 'allocated', 'service_id' => $serviceId, 'organization_id' => $organizationId, 'allocated_at' => now(), 'released_at' => null])->save();
                $this->checkThreshold($pool);

                return $address;
            }
            $this->outbox->publish(GenericEvent::of('ipam.exhausted', 'ip_pool', "{$regionCode}:{$purpose}:v{$family}", ['family' => $family, 'region' => $regionCode, 'purpose' => $purpose]));
            throw new DomainError('ip_pool_exhausted', "No free IPv{$family} address in {$regionCode}/{$purpose}.", 503);
        }, 3);
    }

    public function release(IpAddress $address, int $quarantineDays = 7): void
    {
        if ($address->rdns !== null) {
            $this->setReverseDns($address, null, CommandContext::system('ipam'), 'address released'); // the PTR of the last tenant never follows the address to the next one
        }
        $address->forceFill(['state' => 'quarantine', 'service_id' => null, 'organization_id' => null, 'rdns' => null, 'released_at' => now(), 'reserved_until' => now()->addDays($quarantineDays)])->save();
    }

    public function releaseQuarantine(): int
    {
        return IpAddress::query()->where('state', 'quarantine')->where('reserved_until', '<', now())->update(['state' => 'free', 'reserved_until' => null]);
    }

    /**
     * The reverse record of an address — written down here **and published** into the reverse zone the platform holds
     * (audit §5ae). Until this, `rdns` was a column nothing ever turned into a PTR: every VPS the platform provisioned
     * had no reverse record at all, so mail leaving it was refused by most receivers — while the IPv4 add-on sold
     * `rdns: true`. A null hostname removes the record.
     */
    public function setReverseDns(IpAddress $address, ?string $hostname, ?CommandContext $actor = null, string $reason = 'reverse dns'): IpAddress
    {
        $canonical = $hostname === null || trim($hostname) === '' ? null : Hostname::canonical($hostname);
        $address->forceFill(['rdns' => $canonical])->save();
        try {
            $published = app(DnsService::class)->syncPtr((string) $address->address, $canonical, $actor ?? CommandContext::system('ipam'), $reason);
        } catch (Throwable $e) { // the record stays written down; the nightly drift check and republish put it right
            $this->outbox->publish(GenericEvent::of('ipam.rdns.failed', 'ip_address', (string) $address->address, ['hostname' => $canonical, 'error' => mb_substr($e->getMessage(), 0, 200)], $address->organization_id));

            return $address->refresh();
        }
        if ($published === null && $canonical !== null) {
            // no reverse zone here for this address: the operator has to be delegated one, or the upstream sets the PTR
            $this->outbox->publish(GenericEvent::of('ipam.rdns.unpublished', 'ip_address', (string) $address->address, ['hostname' => $canonical], $address->organization_id));
        }

        return $address->refresh();
    }

    /** Whether the platform can publish a PTR for this address at all (the panel asks before offering the field). */
    public function reverseZoneExists(IpAddress $address): bool
    {
        return app(DnsService::class)->reverseZoneFor((string) $address->address) !== null;
    }

    /** Seed a pool with every host address of a CIDR (IPv4) or a list of /64 subnets (IPv6). */
    public function populate(IpPool $pool, int $limit = 65536): int
    {
        $count = 0;
        [$base, $prefix] = explode('/', $pool->cidr);
        if ($pool->family === 4) {
            $start = ip2long($base);
            $size = 2 ** (32 - (int) $prefix);
            for ($i = 1; $i < $size - 1 && $count < $limit; $i++) {
                $ip = long2ip($start + $i);
                if ($ip === $pool->gateway) {
                    continue;
                }
                IpAddress::query()->firstOrCreate(['address' => $ip], ['pool_id' => $pool->id, 'family' => 4, 'prefix_length' => (int) $prefix, 'state' => 'free']);
                $count++;
            }
        } else {
            $bin = inet_pton($base);
            if ($bin === false) {
                throw new DomainError('ipam_invalid_cidr', "Invalid IPv6 base {$base}", 500);
            }
            $subnets = min($limit, 2 ** max(0, 64 - (int) $prefix));
            for ($i = 0; $i < $subnets; $i++) {
                $hex = bin2hex($bin);
                $prefixHex = substr($hex, 0, 16);
                $subnet = str_pad(dechex(hexdec(substr($prefixHex, 12, 4)) + $i), 4, '0', STR_PAD_LEFT);
                $addr = inet_ntop(hex2bin(substr($prefixHex, 0, 12).$subnet.str_repeat('0', 16)) ?: $bin);
                IpAddress::query()->firstOrCreate(['address' => $addr.'/64'], ['pool_id' => $pool->id, 'family' => 6, 'prefix_length' => 64, 'state' => 'free']);
                $count++;
            }
        }

        return $count;
    }

    private function checkThreshold(IpPool $pool): void
    {
        $total = IpAddress::query()->where('pool_id', $pool->id)->count();
        $free = IpAddress::query()->where('pool_id', $pool->id)->where('state', 'free')->count();
        if ($total > 0 && $free / $total < 0.1) {
            $this->outbox->publish(GenericEvent::of('ipam.threshold', 'ip_pool', $pool->id, ['free' => $free, 'total' => $total, 'cidr' => $pool->cidr]));
        }
    }
}
