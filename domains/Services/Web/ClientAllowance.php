<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Services\IncludedServices;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * What one customer holds on one panel.
 *
 * ISPConfig puts every service of an organization under a single client account, and that account carries the limits
 * the panel enforces: how many sites, how much space, how many databases and mailboxes. The platform wrote them once,
 * from the entitlements of whichever service happened to be provisioning first — so they were wrong for every service
 * after it. Two ordinary web hostings (`sites = 1` each) left the client at `limit_web_domain = 1` and ISPConfig
 * refused the second site **after the customer had paid for it**; a plan change on a small service rewrote the
 * client's limits down to that small plan and took the space from every other site of the same customer.
 *
 * So the limits of a client are the sum over the organization's services on that panel. Two rules make the sum right:
 *
 *  - only services that **carry** their sites are counted. A web hosting that sells „10 webů“ already says
 *    `sites = 10` and `nvme_gb = 50`; its carried sites are those ten, each holding a share of that same space
 *    (`ServiceSites`). Adding them again would count one plan twice.
 *  - a service on its way out still holds its resources at the panel until it is purged, so everything but
 *    TERMINATED counts. The limits drop when the site is really gone, not when the customer clicks cancel.
 */
final class ClientAllowance
{
    /** Numbers that add up across an organization's services, as the panel's limits read them. */
    private const SUMMED = ['sites', 'nvme_gb', 'databases', 'mailboxes', 'cron_concurrency', 'domains', 'ftp_accounts'];

    /** Entitlements that are either on or off: one service with it is enough for the client to have it. */
    private const ANY_OF = ['ssh'];

    /** Numbers the panel reads per site rather than per client: they stay this service's own. */
    private const PER_SITE = ['quota_gb_per_mailbox', 'php_workers', 'backup_generations', 'php_memory_mb'];

    /**
     * What the organization of this service holds on the panel it runs on.
     *
     * @return array<string,int|bool|mixed>
     */
    public static function of(Service $service): array
    {
        $organizationId = (string) ($service->organization_id ?? '');
        $instanceId = (string) ($service->provider_instance_id ?? '');
        $own = (array) $service->entitlements;
        if ($organizationId === '' || $instanceId === '') {
            return $own; // nothing to sum over: the service's own plan is the best answer there is
        }
        $services = Service::query()
            ->where('organization_id', $organizationId)
            ->where('provider_instance_id', $instanceId)
            ->whereNotIn('state', [ServiceStateMachine::TERMINATED])
            ->get()
            ->reject(fn (Service $one) => IncludedServices::isIncluded($one)); // its parent's plan already counts it
        if (! IncludedServices::isIncluded($service) && ! $services->contains(fn (Service $one) => $one->id === $service->id)) {
            $services->push($service); // the service being provisioned right now must count towards its own limits
        }
        $total = [];
        foreach ($services as $one) {
            $entitlements = (array) $one->entitlements;
            foreach (self::SUMMED as $key) {
                if (isset($entitlements[$key]) && is_numeric($entitlements[$key])) {
                    $total[$key] = (int) ($total[$key] ?? 0) + (int) $entitlements[$key];
                }
            }
            foreach (self::ANY_OF as $key) {
                if (! empty($entitlements[$key])) {
                    $total[$key] = true;
                }
            }
        }
        foreach (self::PER_SITE as $key) {
            if (isset($own[$key])) {
                $total[$key] = $own[$key];
            }
        }

        return $total;
    }
}
