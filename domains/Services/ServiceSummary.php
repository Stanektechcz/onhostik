<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Platform\Money\Money;

/**
 * One glance at a service: the plan and its billing (period, next renewal, price, auto-renew), the project, where it
 * runs (region and zone — never the node or the panel behind it), the certificate, the last backup, the uptime monitor
 * and the operations in flight. The API detail carries the full summary; list rows carry the billing part only.
 */
final class ServiceSummary
{
    /** @var array<string, array{key:?string,name:?string,name_en:?string,sla_class:?string}> */
    private array $plans = [];

    /** @return array<string,mixed> */
    public function for(Service $service): array
    {
        $subscription = $this->subscription($service);
        $node = $service->node_id ? Node::query()->find($service->node_id) : null;
        $project = $service->project_id ? Project::query()->find($service->project_id) : null;
        $backup = Backup::query()->where('service_id', $service->id)->where('state', 'available')->orderByDesc('finished_at')->first();
        $monitor = UptimeMonitor::query()->where('service_id', $service->id)->first();
        $active = Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->count();
        $lastFailed = Operation::query()->where('service_id', $service->id)->where('state', Operation::FAILED)->orderByDesc('finished_at')->first();
        $access = (array) (($service->tags ?? [])['access'] ?? []);

        return [
            'plan' => $this->plan($service->plan_version_id),
            'billing' => $this->billing($subscription),
            'project' => $project ? ['id' => $project->id, 'name' => $project->name] : null,
            'location' => ['region' => $service->region_code, 'zone' => $node?->zone],
            'certificate' => $access['certificate'] ?? null,
            'access_domain' => $access['domain'] ?? null,
            'backup' => $backup ? ['last_at' => $backup->finished_at?->toIso8601String(), 'kind' => $backup->kind, 'size_bytes' => $backup->size_bytes, 'offsite' => (bool) $backup->offsite, 'verified' => $backup->verified_at !== null] : null,
            'backups_count' => Backup::query()->where('service_id', $service->id)->where('state', 'available')->count(),
            'monitor' => $monitor ? ['state' => $monitor->state, 'enabled' => (bool) $monitor->enabled, 'last_checked_at' => $monitor->last_checked_at?->toIso8601String(), 'last_status' => $monitor->last_status, 'last_ms' => $monitor->last_ms, 'url' => $monitor->url] : null,
            'operations' => ['active' => $active, 'last_failed' => $lastFailed ? ['kind' => $lastFailed->kind, 'finished_at' => $lastFailed->finished_at?->toIso8601String()] : null],
            // the usage watch's last measurement (level, per-metric share) and the customer's automation policy
            'usage' => is_array(($service->tags ?? [])['usage'] ?? null) ? array_intersect_key($service->tags['usage'], ['level' => 1, 'metrics' => 1, 'checked_at' => 1, 'auto_upgrade_on' => 1]) : null,
            'policy' => ['auto_upgrade' => (bool) (($service->tags ?? [])['policy']['auto_upgrade'] ?? false), 'availability_alerts' => (bool) (($service->tags ?? [])['policy']['availability_alerts'] ?? true)],
            'availability' => in_array($service->family, AvailabilityWatch::FAMILIES, true) ? AvailabilityWatch::of($service) : null,
            'checklist' => $this->checklist($service, $backup, $monitor, $access),
        ];
    }

    /**
     * Onboarding checklist (audit §5e-4): what a freshly provisioned web service still lacks, each item with the
     * workbench tab (and action) that completes it. Computed, never stored — it disappears as the customer works.
     *
     * @return list<array{key:string, done:bool, label:string, label_en:string, hint:string, hint_en:string, tab:?string, action:?string}>
     */
    public function checklist(Service $service, ?Backup $backup, ?UptimeMonitor $monitor, array $access): array
    {
        if (! in_array($service->family, ['web', 'managed'], true)) {
            return [];
        }
        $platformZones = array_map(fn ($z) => strtolower(rtrim((string) $z, '.')), (array) config('onhost.dns.platform_zones', ['onhost.cz']));
        $hostname = (string) ($access['domain'] ?? $service->hostname ?? '');
        $onPlatformZone = false;
        foreach ($platformZones as $zone) {
            if ($zone !== '' && str_ends_with($hostname, '.'.$zone)) {
                $onPlatformZone = true;
            }
        }
        $ownDomain = $hostname !== '' && ! $onPlatformZone || ! empty($service->desired_spec['extra_domains'] ?? []);
        $certificate = (string) ($access['certificate'] ?? '');

        return [
            ['key' => 'domain', 'done' => $ownDomain, 'label' => 'Vlastní doména', 'label_en' => 'Your own domain', 'hint' => $ownDomain ? 'Web běží na vaší doméně.' : 'Web zatím běží jen na technické adrese; spárujte s ním svou doménu.', 'hint_en' => $ownDomain ? 'The site runs on your domain.' : 'The site only answers on its technical address; pair your domain with it.', 'tab' => 'cfg', 'action' => 'subdomain.add'],
            ['key' => 'certificate', 'done' => $certificate === 'issued', 'label' => 'Certifikát HTTPS', 'label_en' => 'HTTPS certificate', 'hint' => $certificate === 'issued' ? 'Certifikát je vystavený.' : ($certificate === 'pending_dns' ? 'Čeká, až doména ukáže na web; vystaví se sám.' : ($certificate === 'requested' ? 'Vystavení probíhá.' : 'Vystavte certifikát Let’s Encrypt jedním klikem.')), 'hint_en' => $certificate === 'issued' ? 'The certificate is issued.' : ($certificate === 'pending_dns' ? 'Waits for the domain to point at the site; issued automatically.' : ($certificate === 'requested' ? 'Being issued.' : 'Issue a Let’s Encrypt certificate with one click.')), 'tab' => 'ssl', 'action' => 'ssl.issue'],
            ['key' => 'backup', 'done' => $backup !== null && $backup->verified_at !== null, 'label' => 'Ověřená záloha', 'label_en' => 'Verified backup', 'hint' => $backup === null ? 'Zatím žádná záloha; první proběhne podle plánu, ruční spustíte hned.' : ($backup->verified_at !== null ? 'Poslední záloha je ověřená.' : 'Záloha existuje, ověření obnovy ještě neproběhlo.'), 'hint_en' => $backup === null ? 'No backup yet; the first runs on schedule, a manual one runs now.' : ($backup->verified_at !== null ? 'The last backup is verified.' : 'A backup exists, its restore test has not run yet.'), 'tab' => 'bkp', 'action' => 'backup'],
            ['key' => 'monitor', 'done' => $monitor !== null && (bool) $monitor->enabled, 'label' => 'Monitoring dostupnosti', 'label_en' => 'Uptime monitoring', 'hint' => $monitor !== null && $monitor->enabled ? 'Web hlídáme každou minutu.' : 'Zapněte hlídání dostupnosti, ať se o výpadku dozvíte první.', 'hint_en' => $monitor !== null && $monitor->enabled ? 'The site is checked every minute.' : 'Switch monitoring on to hear about an outage first.', 'tab' => 'mon', 'action' => 'monitoring.set'],
        ];
    }

    /** The billing part for list rows (one subscription lookup, plan names cached per request). @return array<string,mixed> */
    public function billingFor(Service $service): array
    {
        return ['plan' => $this->plan($service->plan_version_id), 'billing' => $this->billing($this->subscription($service))];
    }

    private function subscription(Service $service): ?Subscription
    {
        return ($service->subscription_id ? Subscription::query()->find($service->subscription_id) : null) ?? Subscription::query()->where('service_id', $service->id)->orderByDesc('created_at')->first();
    }

    /** @return array{key:?string,name:?string,name_en:?string,sla_class:?string}|null */
    private function plan(?string $versionId): ?array
    {
        if ($versionId === null || $versionId === '') {
            return null;
        }
        if (! array_key_exists($versionId, $this->plans)) {
            $version = PlanVersion::query()->with('plan')->find($versionId);
            $this->plans[$versionId] = ['key' => $version?->plan?->key, 'name' => $version?->plan?->localizedName('cs'), 'name_en' => $version?->plan?->localizedName('en'), 'sla_class' => $version?->plan?->sla_class];
        }

        return $this->plans[$versionId];
    }

    /** @return array<string,mixed>|null */
    private function billing(?Subscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }
        $renewsAt = $subscription->cancel_at_period_end ? null : ($subscription->next_renewal_at ?? $subscription->current_period_end);

        return [
            'state' => $subscription->state, 'period' => $subscription->period, 'amount' => Money::minor((int) $subscription->amount_minor, $subscription->currency),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(), 'next_renewal_at' => $renewsAt?->toIso8601String(),
            'auto_renew' => (bool) $subscription->auto_renew, 'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end, 'renewal_failures' => (int) $subscription->renewal_failures,
        ];
    }
}
