<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Collection;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\IncludedServices;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * The further web sites a hosting plan sells. Every plan on the price list states a number of sites — „1 web“,
 * „10 webů“, „50 webů“, and the agency page promises „deset až padesát webů pod jedním účtem“ — but the customer
 * could only ever add another DOMAIN onto the same site (an alias sharing one document root, one PHP version, one
 * certificate and one set of files). The plan's number was even rendered as the limit of those aliases.
 *
 * A further site is a site: its own document root, PHP version, certificate, databases, FTP accounts, backups and
 * its own panel. So it is a service of its own, carried by the one the customer pays for (`IncludedServices`:
 * `tags.billing = included`), provisioned by the ordinary website saga and cancelled with its parent.
 *
 * **The space of the plan is divided between the sites.** ISPConfig sums the `hd_quota` of a client's sites against
 * the client's `limit_web_quota`, so two sites cannot both hold the whole plan — and a number the panel refuses is
 * no way to sell hosting. Every site therefore carries its share in `tags.sites.quota_gb`; the shares never add up
 * to more than the plan, and a share is never cut below what that site already stores (`RESERVE_PCT` on top), because
 * a site over its quota stops being able to write.
 */
final class ServiceSites
{
    /** The smallest share a site can be given. */
    public const MIN_GB = 1;

    /** Headroom kept above what a site already stores when its share is cut. */
    public const RESERVE_PCT = 15;

    public function __construct(
        private readonly ServiceFeatures $features,
        private readonly ServiceService $services,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** How many sites the plan of this service sells (the service's own site counts as one). */
    public static function limit(Service $service): int
    {
        return max(1, (int) data_get($service->entitlements, 'sites', 1));
    }

    /** The service the customer pays for: a carried site belongs to the one carrying it. */
    public static function ownerOf(Service $service): Service
    {
        $parent = IncludedServices::isIncluded($service) ? Service::query()->find((string) data_get($service->tags, 'parent_service_id', '')) : null;

        return $parent instanceof Service && $parent->organization_id === $service->organization_id ? $parent : $service;
    }

    /**
     * Every site of the service, the paid one first — the test copy is a site of the platform's own making and is
     * not one of the plan's, so it is not listed and does not count against the number.
     *
     * @return Collection<int, Service>
     */
    public static function of(Service $owner): Collection
    {
        return collect([$owner])->concat(IncludedServices::of($owner)->reject(fn (Service $child) => data_get($child->tags, 'staging_of') !== null)->all());
    }

    /** The share of the plan's space a site holds, in GB (the whole plan while the service has one site). */
    public static function share(Service $site, Service $owner): int
    {
        $stored = (int) data_get($site->tags, 'sites.quota_gb', 0);

        return $stored > 0 ? $stored : (int) data_get($owner->entitlements, 'nvme_gb', 0);
    }

    /**
     * What the service's own site holds of a plan of `$totalGb`: everything the carried sites do not. A plan change
     * that raised the space has to reach the site the customer actually works on — and one that lowered it must not
     * hand the same gigabytes out twice (`PlanFit` has already refused a plan the shares do not fit into).
     */
    public static function ownShare(Service $owner, int $totalGb): ?int
    {
        if ($totalGb <= 0) {
            return null;
        }
        $others = (int) self::of($owner)->reject(fn (Service $site) => $site->id === $owner->id)->sum(fn (Service $site) => self::share($site, $owner));

        return max(self::MIN_GB, $totalGb - $others);
    }

    /**
     * The plan the customer pays for reaches every site it carries. A carried site keeps only what is its own — the
     * single site it is and its share of the plan's space — and everything else of the plan travels to it: a paid
     * upgrade used to stop at the service it was bought on, so half the customer's sites went on with the numbers
     * and the features of the plan they had left behind.
     *
     * @return int how many sites the plan reached
     */
    public static function spread(Service $owner): int
    {
        if (IncludedServices::isIncluded($owner)) {
            return 0; // a carried site hands a plan to nobody
        }
        $changed = 0;
        foreach (self::of($owner) as $site) {
            if ($site->id === $owner->id) {
                continue;
            }
            $next = array_replace((array) $owner->entitlements, ['sites' => 1, 'nvme_gb' => self::share($site, $owner)]);
            if ($next === (array) $site->entitlements) {
                continue;
            }
            $spec = (array) $site->desired_spec;
            if (is_array($spec['entitlements'] ?? null)) {
                $spec['entitlements'] = $next; // so that a reconcile does not put the old plan back
            }
            $site->forceFill(['entitlements' => $next, 'desired_spec' => $spec])->save();
            $changed++;
        }

        return $changed;
    }

    /**
     * What a new site of `$wantGb` would leave the others: the free part of the plan first, the rest taken from the
     * service's own site, which is the only one the customer is adding to.
     *
     * @return array{share:int, owner_share:int, free:int, total:int, taken_from_owner:int}
     */
    public function division(Service $owner, int $wantGb): array
    {
        $total = (int) data_get($owner->entitlements, 'nvme_gb', 0);
        $sites = self::of($owner);
        $allocated = $sites->sum(fn (Service $site) => self::share($site, $owner));
        $free = max(0, $total - (int) $allocated);
        $share = max(self::MIN_GB, $wantGb > 0 ? $wantGb : $free);
        $fromOwner = max(0, $share - $free);
        $ownerShare = self::share($owner, $owner) - $fromOwner;

        return ['share' => $share, 'owner_share' => $ownerShare, 'free' => $free, 'total' => $total, 'taken_from_owner' => $fromOwner];
    }

    /**
     * Refuses everything that would end in a site nobody can use: one site too many, a name that is taken, a plan
     * that does not offer the PHP version, a share the plan has no room for, or a share cut below what the service's
     * own site already stores.
     *
     * @param  array<string,mixed>  $spec
     * @return array{domain:string, php_version:string, share:int, owner_share:int}
     */
    public function assertRoomFor(Service $owner, array $spec): array
    {
        if (! in_array($owner->family, ['web', 'managed'], true)) {
            throw new DomainError('sites_unsupported', 'Další weby lze přidávat jen k webhostingu.', 422, ['family' => $owner->family]);
        }
        if (IncludedServices::isIncluded($owner)) {
            throw new DomainError('sites_unsupported', 'Tenhle web je součástí jiné služby; další weby přidávejte u ní.', 422);
        }
        $held = self::of($owner)->count();
        if ($held >= self::limit($owner)) {
            throw new DomainError('site_limit_reached', 'Tarif nabízí '.self::limit($owner).' '.(self::limit($owner) === 1 ? 'web' : 'webů').' a tolik jich služba má. Vyšší tarif přidá další.', 409, ['limit' => self::limit($owner), 'held' => $held]);
        }
        $domain = strtolower(trim((string) ($spec['domain'] ?? '')));
        if ($domain === '' || ! preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', $domain) || strlen($domain) > 190) {
            throw new DomainError('action_param_invalid', 'Zadejte doménu webu, například muj-web.cz.', 422, ['field' => 'domain']);
        }
        if (self::of($owner)->contains(fn (Service $site) => strtolower((string) ($site->hostname ?? '')) === $domain)) {
            throw new DomainError('site_exists', 'Web pro tuto doménu už ve službě je.', 409, ['domain' => $domain]);
        }
        if (Service::query()->where('hostname', $domain)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->exists()) {
            throw new DomainError('site_exists', 'Tuto doménu už na ONhostu hostuje jiná služba.', 409, ['domain' => $domain]);
        }
        $php = (string) ($spec['php_version'] ?? data_get($owner->desired_spec, 'php_version', '8.3'));
        $offered = array_map('strval', (array) data_get($owner->entitlements, 'php_versions', []));
        if ($offered !== [] && ! in_array($php, $offered, true)) {
            throw new DomainError('action_param_invalid', 'Tarif nabízí PHP '.implode(', ', $offered).'.', 422, ['field' => 'php_version']);
        }
        $division = $this->division($owner, (int) ($spec['nvme_gb'] ?? 0));
        if ($division['total'] > 0 && $division['owner_share'] < self::MIN_GB) {
            throw new DomainError('site_space_short', 'Na nový web nezbývá místo: tarif má '.$division['total'].' GB a web '.($owner->hostname ?: $owner->name).' jich drží '.self::share($owner, $owner).'. Zmenšete jeho prostor, nebo zvolte vyšší tarif.', 409, ['free' => $division['free'], 'total' => $division['total']]);
        }
        if ($division['taken_from_owner'] > 0) {
            $this->assertOwnerFits($owner, $division['owner_share']);
        }

        return ['domain' => $domain, 'php_version' => $php, 'share' => $division['share'], 'owner_share' => $division['owner_share']];
    }

    /** A share is never cut below what the site already stores, with headroom on top: it would stop being able to write. */
    private function assertOwnerFits(Service $owner, int $share): void
    {
        try {
            $used = (int) ($this->features->resources($owner, 'quotas', true, [])['disk_used_bytes'] ?? 0);
        } catch (Throwable) {
            return; // the panel did not say; the platform does not invent a number and the panel refuses what it cannot do
        }
        $needGb = (int) ceil($used / 1024 ** 3 * (1 + self::RESERVE_PCT / 100));
        if ($used > 0 && $share < max(self::MIN_GB, $needGb)) {
            throw new DomainError('site_space_short', 'Web '.($owner->hostname ?: $owner->name).' už uložil '.round($used / 1024 ** 3, 1).' GB, takže mu nelze nechat jen '.$share.' GB. Zvolte pro nový web menší prostor.', 409, ['used_bytes' => $used, 'would_leave_gb' => $share]);
        }
    }

    /**
     * The new site as a service of its own on the same node, provisioning started. The caller waits for it to become
     * ACTIVE (the ordinary website saga: site, PHP, DNS, certificate, HTTPS).
     *
     * @param  array{domain:string, php_version:string, share:int, owner_share:int}  $plan
     */
    public function create(Service $owner, array $plan, CommandContext $context): Service
    {
        $desired = array_merge((array) $owner->desired_spec, [
            'domain' => $plan['domain'], 'aliases' => [], 'php_version' => $plan['php_version'],
            'placement' => ['instance_id' => $owner->provider_instance_id, 'node_id' => $owner->node_id],
        ]);
        unset($desired['service_id'], $desired['staging_of']);
        $site = Service::query()->create([
            'organization_id' => $owner->organization_id, 'project_id' => $owner->project_id, 'product_key' => $owner->product_key, 'plan_version_id' => $owner->plan_version_id,
            'family' => $owner->family, 'name' => $plan['domain'], 'label' => null, 'state' => ServiceStateMachine::PAID, 'region_code' => $owner->region_code,
            // the plan's features come with it; the space is its own share and it carries no sites of its own
            'entitlements' => array_replace((array) $owner->entitlements, ['sites' => 1, 'nvme_gb' => $plan['share']]),
            'sla_class' => $owner->sla_class, 'tags' => ['parent_service_id' => $owner->id, 'billing' => 'included', 'sites' => ['quota_gb' => $plan['share']]],
            'desired_spec' => $desired, 'hostname' => $plan['domain'], 'node_id' => $owner->node_id, 'provider_instance_id' => $owner->provider_instance_id,
        ]);
        $this->services->startProvisioning($site, $context);
        $this->audit->record($context->withScope($owner->organization_id), 'service.site.create', 'succeeded', ['site_service_id' => $site->id, 'domain' => $plan['domain'], 'nvme_gb' => $plan['share']], 'service', $owner->id);
        $this->outbox->publish(GenericEvent::of('service.site.created', 'service', $owner->id, ['site_service_id' => $site->id, 'domain' => $plan['domain'], 'nvme_gb' => $plan['share'], 'php_version' => $plan['php_version']], $owner->organization_id));

        return $site;
    }

    /** The carried site this action names, or a refusal that does not say whether somebody else's site exists. */
    public function siteOf(Service $owner, string $siteId): Service
    {
        $site = self::of($owner)->first(fn (Service $candidate) => $candidate->id === $siteId && $candidate->id !== $owner->id);
        if (! $site instanceof Service) {
            throw DomainError::notFound('site');
        }

        return $site;
    }

    /** Cancels one carried site: its own cancellation, so it is archived before anything of it is removed. */
    public function remove(Service $owner, Service $site, CommandContext $context, string $operationId): Operation
    {
        $operation = $this->services->requestAction($site, 'terminate', CommandContext::system('site removed from '.$owner->id), "site:terminate:{$operationId}:{$site->id}", ['reason' => 'web odebrán ze služby']);
        $this->audit->record($context->withScope($owner->organization_id), 'service.site.delete', 'succeeded', ['site_service_id' => $site->id, 'domain' => $site->hostname, 'operation_id' => $operation->id], 'service', $owner->id);
        $this->outbox->publish(GenericEvent::of('service.site.removed', 'service', $owner->id, ['site_service_id' => $site->id, 'domain' => $site->hostname], $owner->organization_id));

        return $operation;
    }

    /**
     * What the panel shows: every site of the service with its share and state.
     *
     * @return list<array<string,mixed>>
     */
    public function listing(Service $owner): array
    {
        return self::of($owner)->map(fn (Service $site) => [
            'service_id' => $site->id, 'domain' => (string) ($site->hostname ?: $site->name), 'primary' => $site->id === $owner->id,
            'state' => $site->state, 'php_version' => (string) data_get($site->desired_spec, 'php_version', ''), 'nvme_gb' => self::share($site, $owner),
            'created_at' => $site->created_at?->toIso8601String(),
        ])->values()->all();
    }
}
