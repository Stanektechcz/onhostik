<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Support\Collection;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Net\DnsLookup;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * A status page per organization (audit §5j-5): what a customer's own customers may see — the monitors the customer
 * runs (host names only, never the full URL), the platform components behind the customer's services, incidents and
 * maintenance windows that touch them. Off by default; the customer switches it on in the account settings and gets
 * `/stav/<slug>` plus a badge. Their own host name (audit §5k-3): `status.<their-domain>` as a CNAME to the portal
 * host, verified here, then served by the StatusHost middleware; the edge certificate comes from on-demand TLS whose
 * "ask" endpoint is `hostAllowed()`.
 */
final class OrganizationStatusService
{
    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public function enabled(Organization $organization): bool
    {
        return (bool) data_get($organization->settings, 'status_page.enabled', false);
    }

    /** @param  array{enabled?:bool, title?:string, show_monitors?:bool, show_incidents?:bool, domain?:?string}  $input @return array<string,mixed> */
    public static function settings(array $input, array $current = []): array
    {
        $title = isset($input['title']) ? mb_substr(trim((string) $input['title']), 0, 80) : ($current['title'] ?? null);
        if ($title !== null && $title !== '' && mb_strlen($title) < 2) {
            throw new DomainError('status_page_title_invalid', 'The title is 2–80 characters.', 422, ['field' => 'title']);
        }
        $out = [
            'enabled' => (bool) ($input['enabled'] ?? $current['enabled'] ?? false), 'title' => $title !== '' ? $title : null,
            'show_monitors' => (bool) ($input['show_monitors'] ?? $current['show_monitors'] ?? true), 'show_incidents' => (bool) ($input['show_incidents'] ?? $current['show_incidents'] ?? true),
            'domain' => $current['domain'] ?? null, 'domain_verified_at' => $current['domain_verified_at'] ?? null,
        ];
        if (array_key_exists('domain', $input)) { // the customer's own host name (audit §5k-3); a change starts the verification over
            $domain = strtolower(trim((string) ($input['domain'] ?? '')));
            if ($domain === '') {
                $out['domain'] = null;
                $out['domain_verified_at'] = null;
            } else {
                if (! preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain) || substr_count($domain, '.') < 2) {
                    throw new DomainError('status_domain_invalid', 'Use a host name like status.your-domain.cz.', 422, ['field' => 'domain']);
                }
                if ($domain === self::customCname()) {
                    throw new DomainError('status_domain_invalid', 'That is the portal host itself.', 422, ['field' => 'domain']);
                }
                if ($domain !== ($current['domain'] ?? null)) {
                    $out['domain'] = $domain;
                    $out['domain_verified_at'] = null;
                }
            }
        }

        return $out;
    }

    /** The CNAME target the customer's host must point at. */
    public static function customCname(): string
    {
        return strtolower((string) (config('onhost.status.custom_cname') ?: (parse_url((string) config('onhost.portal_url'), PHP_URL_HOST) ?: 'portal.onhost.cz')));
    }

    /** Verifies the CNAME of the organization's status host; records the outcome. @return array<string,mixed> */
    public function verifyDomain(Organization $organization, CommandContext $context): array
    {
        $settings = (array) data_get($organization->settings, 'status_page', []);
        $domain = (string) ($settings['domain'] ?? '');
        if ($domain === '') {
            throw new DomainError('status_domain_missing', 'Set the host name first.', 409);
        }
        $target = self::customCname();
        $targets = DnsLookup::cname($domain);
        $verified = in_array($target, $targets, true);
        $taken = Organization::query()->whereKeyNot($organization->id)->where('settings->status_page->domain', $domain)->whereNotNull('settings->status_page->domain_verified_at')->exists();
        if ($verified && $taken) {
            throw new DomainError('status_domain_taken', 'This host name already serves another status page.', 409, ['field' => 'domain']);
        }
        $settings['domain_verified_at'] = $verified ? now()->toIso8601String() : null;
        $settings['domain_checked_at'] = now()->toIso8601String();
        $organization->forceFill(['settings' => array_merge((array) ($organization->settings ?? []), ['status_page' => $settings])])->save();
        $this->audit->record($context->withScope($organization->id), 'status_page.domain.verify', $verified ? 'succeeded' : 'failed', ['domain' => $domain, 'targets' => $targets, 'expected' => $target], 'organization', $organization->id);
        if ($verified) {
            $this->outbox->publish(GenericEvent::of('status_page.domain.verified', 'organization', $organization->id, ['domain' => $domain], $organization->id));
        }

        return ['domain' => $domain, 'verified' => $verified, 'expected_cname' => $target, 'found' => $targets, 'url' => $verified ? "https://{$domain}/" : null];
    }

    /** Re-checks every host name that is set and not verified yet (scheduled hourly). */
    public function verifyPending(): int
    {
        $count = 0;
        foreach (Organization::query()->whereNotNull('settings->status_page->domain')->whereNull('settings->status_page->domain_verified_at')->limit(200)->get() as $organization) {
            try {
                $count += $this->verifyDomain($organization, CommandContext::system('status.verify'))['verified'] ? 1 : 0;
            } catch (DomainError) {
                // taken or missing: nothing to do here, the customer sees it in the panel
            }
        }

        return $count;
    }

    /** The organization whose verified, enabled status page lives on this host; null for every other host. */
    public function forHost(string $host): ?Organization
    {
        $host = strtolower(trim($host, '. '));
        if ($host === '') {
            return null;
        }
        $organization = Organization::query()->where('settings->status_page->domain', $host)->whereNotNull('settings->status_page->domain_verified_at')->first();

        return $organization !== null && $this->enabled($organization) ? $organization : null;
    }

    /** On-demand TLS "ask" (Caddy `on_demand_tls { ask }`): may the edge issue a certificate for this host? */
    public function hostAllowed(string $host): bool
    {
        return $this->forHost($host) !== null;
    }

    /** @return array<string,mixed> the public projection (404 when the page is off) */
    public function build(Organization $organization, int $days = 30): array
    {
        if (! $this->enabled($organization)) {
            throw DomainError::notFound('status_page');
        }
        $settings = (array) data_get($organization->settings, 'status_page', []);
        $services = Service::query()->where('organization_id', $organization->id)->whereIn('state', ['ACTIVE', 'DEGRADED', 'SUSPENDED', 'PROVISIONING'])->get(['id', 'family', 'region_code', 'name', 'state']);
        $serviceIds = $services->pluck('id')->all();
        $componentKeys = $this->componentKeys($services);
        $components = StatusComponent::query()->whereIn('key', $componentKeys)->where('public', true)->orderBy('sort')->get()->map(fn (StatusComponent $c) => ['key' => $c->key, 'name' => $c->name, 'state' => $c->state])->values()->all();
        $monitors = ($settings['show_monitors'] ?? true)
            ? UptimeMonitor::query()->where('organization_id', $organization->id)->where('enabled', true)->orderBy('url')->limit(50)->get()->map(fn (UptimeMonitor $m) => [
                'host' => (string) (parse_url($m->url, PHP_URL_HOST) ?: $m->url), 'state' => $m->state, 'ms' => $m->last_ms, 'checked_at' => $m->last_checked_at?->toIso8601String(),
            ])->values()->all()
            : [];
        $since = now()->subDays(max(1, min(90, $days)));
        $incidents = ($settings['show_incidents'] ?? true)
            ? Incident::query()->where('visibility', 'public')->where(fn ($q) => $q->whereNotIn('state', [IncidentStateMachine::RESOLVED, IncidentStateMachine::POSTMORTEM])->orWhere('resolved_at', '>=', now()->subDays(7)))->where('started_at', '>=', $since->copy()->subDays(30))->orderByDesc('started_at')->limit(50)->get()
                ->filter(fn (Incident $i) => array_intersect((array) $i->affected_organizations, [$organization->id]) !== [] || array_intersect((array) $i->affected_services, $serviceIds) !== [] || array_intersect((array) $i->components, $componentKeys) !== [])
                ->map(fn (Incident $i) => ['number' => $i->number, 'title' => $i->title, 'severity' => $i->severity, 'state' => $i->state, 'started_at' => $i->started_at?->toIso8601String(), 'resolved_at' => $i->resolved_at?->toIso8601String()])->values()->all()
            : [];
        $maintenance = Maintenance::query()->whereIn('state', ['planned', 'approved', 'in_progress'])->where('ends_at', '>=', now())->orderBy('starts_at')->limit(20)->get()
            ->filter(fn (Maintenance $m) => array_intersect((array) $m->affected_services, $serviceIds) !== [] || array_intersect((array) $m->components, $componentKeys) !== [])
            ->map(fn (Maintenance $m) => ['number' => $m->number, 'title' => $m->title, 'starts_at' => $m->starts_at?->toIso8601String(), 'ends_at' => $m->ends_at?->toIso8601String(), 'state' => $m->state])->values()->all();
        $overall = 'operational';
        foreach ($components as $component) {
            if (StatusComponent::rank($component['state']) > StatusComponent::rank($overall)) {
                $overall = $component['state'];
            }
        }
        $down = collect($monitors)->where('state', 'down')->count();
        if ($down > 0) {
            $overall = StatusComponent::rank('degraded') > StatusComponent::rank($overall) ? 'degraded' : $overall;
        }
        if (collect($incidents)->contains(fn ($i) => ! in_array($i['state'], [IncidentStateMachine::RESOLVED, IncidentStateMachine::POSTMORTEM], true) && in_array($i['severity'], ['p1', 'p2'], true))) {
            $overall = 'major_outage';
        }

        return [
            'slug' => $organization->slug, 'title' => (string) ($settings['title'] ?: $organization->name), 'overall' => $overall,
            'components' => $components, 'monitors' => $monitors, 'incidents' => $incidents, 'maintenance' => $maintenance,
            'services' => $services->count(), 'domain' => ! empty($settings['domain_verified_at']) ? ($settings['domain'] ?? null) : null, 'generated_at' => now()->toIso8601String(),
        ];
    }

    /** Badge for the customer's own site: the overall state as a small SVG. */
    public function badgeSvg(string $overall, string $title): string
    {
        [$label, $color] = match ($overall) {
            'operational' => ['Vše v provozu', '#1f7a3f'], 'degraded' => ['Omezený provoz', '#b8860b'], 'major_outage', 'partial_outage' => ['Výpadek', '#b3261e'], 'maintenance' => ['Údržba', '#3b5bdb'], default => [$overall, '#555'],
        };
        $text = mb_substr($title, 0, 24).' · '.$label;
        $width = 24 + (int) (mb_strlen($text) * 6.6);
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="22" role="img" aria-label="'.$safe.'"><rect width="'.$width.'" height="22" rx="4" fill="'.$color.'"/><text x="10" y="15" font-family="system-ui,Segoe UI,sans-serif" font-size="11" fill="#fff">'.$safe.'</text></svg>';
    }

    /** @param  Collection<int, Service>  $services @return list<string> */
    private function componentKeys(Collection $services): array
    {
        $keys = [];
        $default = (string) config('onhost.default_region', 'cz1');
        foreach ($services as $service) {
            $region = (string) ($service->region_code ?: $default);
            $keys[] = match ($service->family) {
                'web' => "web-{$region}", 'cloud' => "cloud-{$region}", 'game' => "games-{$region}", 'apps' => "apps-{$region}", 'managed' => 'managed', 'mail' => 'mail', 'domain' => 'domains', default => null,
            };
        }
        $keys[] = 'dns';
        $keys[] = 'portal';

        return array_values(array_unique(array_filter($keys)));
    }
}
