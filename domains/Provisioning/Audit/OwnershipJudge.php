<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Audit;

use Onhost\Domain\Services\Mail\MailDomains;

/**
 * Whose a record was, decided from the logged evidence and the platform's own tables.
 *
 * OWN — the acting service's. FOREIGN_PLATFORM — another platform service's (owner service null when the platform
 * made it but no longer knows for whom). FOREIGN_UNMANAGED — nothing on the platform owns it: a historical resource the
 * owner rules make untouchable. UNKNOWN — the logs hold no evidence; the operator looks it up in sys_datalog.
 * When the evidence disagrees, the worst reading wins and the row says so.
 */
final class OwnershipJudge
{
    public const OWN = 'OWN';

    public const FOREIGN_PLATFORM = 'FOREIGN_PLATFORM';

    public const FOREIGN_UNMANAGED = 'FOREIGN_UNMANAGED';

    public const UNKNOWN = 'UNKNOWN';

    public function __construct(private readonly PlatformOwnerMap $map, private readonly IspConfigOwnershipEvidence $evidence) {}

    /**
     * @param  list<string>  $instanceKeys
     * @return array{verdict:string, owner_service_id:?string, owner_organization_id:?string, owner_site:?int, owner_name:?string, owner_address:?string, evidence_call_ids:list<string>, notes:list<string>}
     */
    public function record(?string $acting, array $instanceKeys, string $kind, int $id): array
    {
        $owners = $this->evidence->owners($instanceKeys, $kind, $id);
        if ($owners === []) {
            return self::verdict(self::UNKNOWN, null, null, [], []);
        }
        $readings = array_map(fn (array $owner) => $this->reading($acting, $kind, $owner), $owners);

        return $this->worst($acting, $readings, $owners);
    }

    /** A mailbox named by its address (fetchmail): its domain decides. */
    public function address(?string $acting, string $address): array
    {
        $owner = ['address' => mb_strtolower($address), 'mail_domain' => MailDomains::domainOf($address), 'via' => 'parameter', 'call' => null];

        return $this->worst($acting, [$this->reading($acting, 'mailbox', $owner)], [$owner]);
    }

    /**
     * @param  array<string,mixed>  $owner
     * @return array{0:string, 1:?string, 2?:string} verdict, owner service, why the owner is not known
     */
    private function reading(?string $acting, string $kind, array $owner): array
    {
        $mail = in_array($kind, ['mailbox', 'mail_alias'], true);
        $services = $mail ? $this->mailServices($owner) : $this->webServices($owner);
        if ($services === null) {
            return [self::FOREIGN_UNMANAGED, null];
        }
        if (! $mail && count($services) > 1) {
            // a web record is one service's; several only share a legacy name prefix (a null name_prefix falls back to
            // the id's last six characters), so the name cannot say whose it is — never clear it as the acting service's
            return [self::FOREIGN_PLATFORM, null, 'shared_prefix'];
        }
        if ($acting !== null && in_array($acting, $services, true)) {
            return [self::OWN, $acting];
        }

        return [self::FOREIGN_PLATFORM, count($services) === 1 ? $services[0] : null]; // [] = platform-made, owner no longer known
    }

    /**
     * @param  array<string,mixed>  $owner
     * @return list<string>|null services; [] = the platform made it; null = nothing on the platform owns it
     */
    private function webServices(array $owner): ?array
    {
        $instance = (string) ($owner['instance_key'] ?? '');
        $site = $owner['site'] ?? null;
        if (is_int($site) && ($service = $this->map->serviceOfSite($instance, $site)) !== null) {
            return [$service];
        }
        $name = $owner['name'] ?? null;
        if (is_string($name) && ($services = $this->map->servicesOfName($name)) !== []) {
            return $services;
        }
        $platformMade = ($owner['via'] ?? '') === 'created' || (is_int($site) && $this->evidence->createdSite($instance, $site)) || (is_string($name) && PlatformOwnerMap::prefixOf($name) !== null);

        return $platformMade ? [] : null;
    }

    /**
     * @param  array<string,mixed>  $owner
     * @return list<string>|null
     */
    private function mailServices(array $owner): ?array
    {
        $services = $this->map->servicesOfMailDomain((string) ($owner['mail_domain'] ?? ''));
        if ($services !== []) {
            return $services;
        }

        return ($owner['via'] ?? '') === 'created' ? [] : null;
    }

    /**
     * @param  list<array{0:string, 1:?string, 2?:string}>  $readings
     * @param  list<array<string,mixed>>  $owners
     */
    private function worst(?string $acting, array $readings, array $owners): array
    {
        $actingOrganization = $this->map->organizationOf($acting);
        $rank = fn (array $r): int => match (true) {
            $r[0] === self::FOREIGN_UNMANAGED => 4,
            $r[0] === self::FOREIGN_PLATFORM && $r[1] !== null && $this->map->organizationOf($r[1]) !== $actingOrganization => 3,
            $r[0] === self::FOREIGN_PLATFORM && $r[1] === null => 2,
            $r[0] === self::FOREIGN_PLATFORM => 1,
            default => 0,
        };
        $pick = 0;
        foreach ($readings as $i => $reading) {
            $pick = $rank($reading) > $rank($readings[$pick]) ? $i : $pick;
        }
        $notes = count(array_unique(array_map(fn (array $r) => $r[0].'|'.$r[1], $readings))) > 1 ? ['conflicting_evidence'] : [];
        if ($readings[$pick][0] === self::FOREIGN_PLATFORM && $readings[$pick][1] === null) {
            $notes[] = $readings[$pick][2] ?? 'owner_service_unknown';
        }

        return self::verdict($readings[$pick][0], $readings[$pick][1], $owners[$pick], array_values(array_filter(array_column($owners, 'call'))), $notes, $this->map->organizationOf($readings[$pick][1]));
    }

    /**
     * @param  array<string,mixed>|null  $owner
     * @param  list<string>  $calls
     * @param  list<string>  $notes
     */
    private static function verdict(string $verdict, ?string $service, ?array $owner, array $calls, array $notes, ?string $organization = null): array
    {
        return [
            'verdict' => $verdict, 'owner_service_id' => $service, 'owner_organization_id' => $organization,
            'owner_site' => $owner['site'] ?? null, 'owner_name' => $owner['name'] ?? null, 'owner_address' => $owner['address'] ?? null,
            'evidence_call_ids' => $calls, 'notes' => $notes,
        ];
    }
}
