<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\StagingLink;
use Onhost\Domain\Services\Web\ServiceSites;
use Onhost\Platform\Errors\DomainError;

/**
 * A plan change has to fit what the service already holds. Nothing checked it: a web hosting with three sites could
 * be moved to a plan that sells one, and the two the plan no longer pays for went on running; the plan's space could
 * drop below what the sites already have allocated, which the panel then refuses — after the order was paid.
 *
 * What is checked here is only what the platform itself knows, without asking a panel: how many sites the service
 * carries, how their shares of the plan's space add up, what it has stored, whether it has a test copy, and which PHP
 * version its sites run. A number that lives at the panel (databases, mailboxes, FTP accounts) is deliberately NOT
 * checked, for two reasons: the price list would depend on a remote call that can be slow or down, and those limits
 * are already enforced where they matter — `ServiceFeatures` counts them at the panel before every new one, so after
 * a downgrade the customer keeps what they have and cannot add more. A site, by contrast, is capacity the customer
 * is billed for, and space is enforced by the panel itself.
 */
final class PlanFit
{
    /**
     * What the service holds that the target plan would not cover.
     *
     * @param  array<string,mixed>  $entitlements  the target plan's entitlements
     * @return list<array{key:string, message:string, have:int|string, offer:int|string}>
     */
    public function shortfalls(Service $service, array $entitlements): array
    {
        if (! in_array($service->family, ['web', 'managed'], true) || IncludedServices::isIncluded($service)) {
            return [];
        }
        $out = [];
        $sites = ServiceSites::of($service);
        $offered = max(1, (int) ($entitlements['sites'] ?? 1));
        if ($sites->count() > $offered) {
            $extra = $sites->reject(fn (Service $site) => $site->id === $service->id)->map(fn (Service $site) => (string) ($site->hostname ?: $site->name));
            $out[] = ['key' => 'sites', 'have' => $sites->count(), 'offer' => $offered,
                'message' => 'Tarif nabízí '.$offered.' '.($offered === 1 ? 'web' : 'webů').' a služba má '.$sites->count().'. Nejdřív odeberte: '.implode(', ', $extra->take(5)->all()).($extra->count() > 5 ? ' a další' : '').'.'];
        }
        $total = (int) ($entitlements['nvme_gb'] ?? 0);
        if ($total > 0) {
            // the further sites hold a fixed share; the service's own site takes what the plan leaves, so what has to fit
            // is the shares of the others — with one site there is nothing to divide and any plan fits
            $carried = (int) $sites->reject(fn (Service $site) => $site->id === $service->id)->sum(fn (Service $site) => ServiceSites::share($site, $service));
            if ($carried > 0 && $carried >= $total) {
                $out[] = ['key' => 'nvme_gb', 'have' => $carried, 'offer' => $total,
                    'message' => 'Tarif nabízí '.$total.' GB a další weby služby mají přidělených '.$carried.' GB, takže na hlavní web by nezbylo nic. Zmenšete prostor některého webu, nebo některý odeberte.'];
            }
            $usedGb = (int) ceil((int) data_get($service->tags, 'usage.metrics.disk.used', 0) / 1024 ** 3);
            if ($usedGb > $total) {
                $out[] = ['key' => 'disk_used', 'have' => $usedGb, 'offer' => $total,
                    'message' => 'Služba má uloženo '.$usedGb.' GB, víc, než tarif nabízí ('.$total.' GB).'];
            }
        }
        if (($entitlements['staging'] ?? false) === false && StagingLink::query()->where('service_id', $service->id)->exists()) {
            $out[] = ['key' => 'staging', 'have' => 'ano', 'offer' => 'ne',
                'message' => 'Tarif nenabízí testovací kopii webu a služba ji má. Nejdřív ji odeberte.'];
        }
        $versions = array_map('strval', (array) ($entitlements['php_versions'] ?? []));
        if ($versions !== []) {
            $running = $sites->map(fn (Service $site) => (string) data_get($site->desired_spec, 'php_version', ''))->filter()->unique();
            $missing = $running->reject(fn (string $version) => in_array($version, $versions, true));
            if ($missing->isNotEmpty()) {
                $out[] = ['key' => 'php_versions', 'have' => $missing->implode(', '), 'offer' => implode(', ', $versions),
                    'message' => 'Weby služby běží na PHP '.$missing->implode(', ').', které tarif nenabízí ('.implode(', ', $versions).'). Přepněte je dřív, než tarif změníte.'];
            }
        }

        return $out;
    }

    /** Refuses the change and names everything that stands in its way, so the customer fixes it in one go. */
    public function assertFits(Service $service, array $entitlements): void
    {
        $shortfalls = $this->shortfalls($service, $entitlements);
        if ($shortfalls === []) {
            return;
        }

        throw new DomainError('plan_change_does_not_fit', implode(' ', array_column($shortfalls, 'message')), 409, ['shortfalls' => $shortfalls]);
    }
}
