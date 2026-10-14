<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Services\Freshness;

/**
 * W2-6: the public view of the web hosting infrastructure, one entry per node under an alias ("Webhosting · Praha · 2") — never a
 * node name, host name or address, and nothing that belongs to a customer. The state of an entry is what the platform last
 * measured, printed with the time of that measurement:
 *
 *   operational  the usage reading of the node is current (Freshness `node_usage`)
 *   maintenance  the node is in maintenance or inside an approved window that names it (a plan, not a measurement)
 *   down         the platform's own record says the node is down
 *   unknown      the last reading is old, failed or was never taken — "neověřeno", never shown as fine
 *
 * It also lists approved / running maintenance windows (title, text, time, which entries) and the public incidents. The answer is
 * kept for a minute so a crowd on the page costs the control plane one computation a minute.
 */
final class InfrastructureStatus
{
    public const CACHE_KEY = 'status:infrastructure:v1';

    public const CACHE_SECONDS = 60;

    /** Node roles the page speaks about, and the word for each. */
    private const ROLES = ['web' => ['Webhosting', 'Web hosting'], 'managed' => ['Managed hosting', 'Managed hosting']];

    private const NODE_STATES = [Node::ACTIVE, 'drain', 'draining', 'maintenance', 'down'];

    private const LABELS = [
        'operational' => ['Měření v pořádku', 'Measurement fine'], 'maintenance' => ['Plánovaná údržba', 'Planned maintenance'],
        'down' => ['Mimo provoz', 'Down'], 'unknown' => ['Neověřeno', 'Not verified'],
    ];

    public function __construct(private readonly IncidentService $incidents) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->build());
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        $regions = Region::query()->pluck('name', 'code')->all();
        $windows = Maintenance::query()->whereIn('state', ['approved', 'in_progress'])->where('ends_at', '>=', now())->orderBy('starts_at')->limit(20)->get();
        $inWindow = [];
        foreach ($windows as $window) {
            if ($window->starts_at <= now()) {
                foreach ((array) $window->affected_nodes as $nodeId) {
                    $inWindow[(string) $nodeId] = true;
                }
            }
        }
        $keys = [];
        $components = [];
        $ordinal = [];
        foreach (Node::query()->whereIn('role', array_keys(self::ROLES))->whereIn('state', self::NODE_STATES)->orderBy('created_at')->orderBy('id')->get() as $node) {
            $group = $node->role.'-'.$node->region_code;
            $number = $ordinal[$group] = ($ordinal[$group] ?? 0) + 1;
            $region = (string) ($regions[$node->region_code] ?? strtoupper((string) $node->region_code));
            $key = $group.'-'.$number;
            $keys[(string) $node->id] = $key;
            $seen = Freshness::assess('node_usage', data_get($node->usage, 'sampled_at'));
            $state = match (true) {
                $node->state === 'maintenance' || isset($inWindow[(string) $node->id]) => 'maintenance',
                $node->state === 'down' => 'down',
                $seen['state'] === Freshness::CURRENT => 'operational',
                default => 'unknown',
            };
            $components[] = [
                'key' => $key, 'name' => ['cs' => self::ROLES[$node->role][0]." · {$region} · {$number}", 'en' => self::ROLES[$node->role][1]." · {$region} · {$number}"],
                'group' => $region, 'state' => $state, 'label' => ['cs' => self::LABELS[$state][0], 'en' => self::LABELS[$state][1]],
                'observed_at' => $seen['observed_at'], 'basis' => $state === 'operational' || $state === 'unknown' ? 'measurement' : ($state === 'maintenance' ? 'plan' : 'platform_record'),
            ];
        }
        $summary = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($components as $component) {
            $summary[$component['state']]++;
        }

        return [
            'generated_at' => now()->toIso8601String(), 'cache_seconds' => self::CACHE_SECONDS, 'summary' => $summary, 'components' => $components,
            'maintenance' => $windows->map(fn (Maintenance $m) => [
                'number' => $m->number, 'title' => $m->title, 'text' => ['cs' => $m->textFor('cs'), 'en' => $m->textFor('en')], 'state' => $m->state === 'in_progress' || $m->starts_at <= now() ? 'in_progress' : 'planned',
                'starts_at' => $m->starts_at->toIso8601String(), 'ends_at' => $m->ends_at->toIso8601String(),
                'components' => array_values(array_filter(array_map(fn ($id) => $keys[(string) $id] ?? null, (array) $m->affected_nodes))), 'families' => array_values((array) $m->affected_families),
            ])->values()->all(),
            'incidents' => array_map(fn (array $i) => array_intersect_key($i, array_flip(['number', 'title', 'sev', 'state', 'state_label', 'components', 'started_at', 'resolved_at'])), $this->incidents->publicStatus(7)['incidents']),
            'notice' => [
                'cs' => 'Každý údaj je z posledního měření uvedeného u položky. Co nemá čerstvé měření, je „neověřeno“ — ne v pořádku. Stránka nic nezaručuje o dostupnosti jednotlivého webu.',
                'en' => 'Each reading is from the last measurement shown with the entry. What has no fresh measurement is "not verified", not fine. The page guarantees nothing about the availability of any single site.',
            ],
        ];
    }
}
