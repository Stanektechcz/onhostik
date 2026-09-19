<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Incidents\IncidentService;
use Onhost\Domain\Incidents\IncidentStateMachine;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Chargeback analytics (audit §5j-6): the reasons customers leave, clustered per product, per node and per theme
 * (performance, price, reliability, support, features, moving away). A cluster that crosses the threshold inside the
 * window opens one internal incident on the matching status component — a slow node or a broken template lands in
 * the incident queue, not only in the refund ledger. One incident per cluster while it is open.
 */
final class ChargebackAnalyst
{
    /** theme => keyword patterns (Czech and English, case-insensitive) */
    public const THEMES = [
        'performance' => '/pomal|slow|lag|výkon|vykon|latenc|načít|nacit|timeout|přetíž|pretiz/iu',
        'reliability' => '/výpad|vypad|down|nefung|nejede|broken|chyb|error|pad[áa]|crash|nedostup/iu',
        'price' => '/cen[aeuy]|drah|price|expensive|levn|cheaper|slev|discount/iu',
        'support' => '/podpor|support|odpov|response|ticket|nikdo|help/iu',
        'features' => '/chyb[íi] |missing|funkc|feature|nepodporuj|not supported|verz|version/iu',
        'moving' => '/stěhuj|stehuj|jinam|konkurenc|jin[éy]ho poskytovatel|other provider|moving|přech[áa]z|prechaz|migr/iu',
    ];

    public function __construct(private readonly IncidentService $incidents, private readonly OutboxPublisher $outbox) {}

    /**
     * @return array{days:int, total:int, refunded:Money|null, by_product:list<array<string,mixed>>, by_node:list<array<string,mixed>>, by_theme:list<array<string,mixed>>, clusters:list<array<string,mixed>>}
     */
    public function analytics(int $days = 90): array
    {
        $days = max(1, min(365, $days));
        $requests = ChargebackRequest::query()->where('created_at', '>=', now()->subDays($days))->where('state', '!=', ChargebackRequest::WITHDRAWN)->get();
        $services = Service::query()->withTrashed()->whereIn('id', $requests->pluck('service_id'))->get()->keyBy('id');
        $nodes = Node::query()->whereIn('id', $services->pluck('node_id')->filter()->unique())->get()->keyBy('id');
        $byProduct = [];
        $byNode = [];
        $byTheme = [];
        $refunded = 0;
        $currency = (string) ($requests->first()?->currency ?? 'CZK');
        foreach ($requests as $request) {
            $service = $services->get($request->service_id);
            $product = (string) ($service?->product_key ?? 'unknown');
            $family = (string) ($service?->family ?? 'unknown');
            $theme = self::theme((string) $request->reason);
            $byProduct[$product] = $byProduct[$product] ?? ['product_key' => $product, 'family' => $family, 'count' => 0, 'refunded_minor' => 0, 'themes' => []];
            $byProduct[$product]['count']++;
            $byProduct[$product]['refunded_minor'] += $request->state === ChargebackRequest::REFUNDED ? (int) $request->refund_minor : 0;
            $byProduct[$product]['themes'][$theme] = ($byProduct[$product]['themes'][$theme] ?? 0) + 1;
            $nodeId = (string) ($service?->node_id ?? '');
            if ($nodeId !== '') {
                $node = $nodes->get($nodeId);
                $byNode[$nodeId] = $byNode[$nodeId] ?? ['node_id' => $nodeId, 'node' => $node?->name ?? $nodeId, 'region' => $node?->region_code, 'role' => $node?->role, 'count' => 0, 'themes' => []];
                $byNode[$nodeId]['count']++;
                $byNode[$nodeId]['themes'][$theme] = ($byNode[$nodeId]['themes'][$theme] ?? 0) + 1;
            }
            $byTheme[$theme] = $byTheme[$theme] ?? ['theme' => $theme, 'count' => 0, 'examples' => []];
            $byTheme[$theme]['count']++;
            if (count($byTheme[$theme]['examples']) < 3 && trim((string) $request->reason) !== '') {
                $byTheme[$theme]['examples'][] = mb_substr(trim((string) $request->reason), 0, 120);
            }
            $refunded += $request->state === ChargebackRequest::REFUNDED ? (int) $request->refund_minor : 0;
        }
        $sort = fn (array &$rows) => usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);
        $byProduct = array_values($byProduct);
        $byNode = array_values($byNode);
        $byTheme = array_values($byTheme);
        $sort($byProduct);
        $sort($byNode);
        $sort($byTheme);
        $threshold = $this->threshold();
        $clusters = [];
        foreach ($byNode as $row) {
            if ($row['count'] >= $threshold) {
                $clusters[] = ['key' => 'node:'.$row['node_id'], 'kind' => 'node', 'label' => $row['node'], 'count' => $row['count'], 'top_theme' => self::top($row['themes'])];
            }
        }
        foreach ($byProduct as $row) {
            if ($row['count'] >= $threshold) {
                $clusters[] = ['key' => 'product:'.$row['product_key'], 'kind' => 'product', 'label' => $row['product_key'], 'count' => $row['count'], 'top_theme' => self::top($row['themes'])];
            }
        }

        return ['days' => $days, 'total' => $requests->count(), 'refunded' => $requests->isEmpty() ? null : Money::minor($refunded, $currency), 'threshold' => $threshold, 'by_product' => $byProduct, 'by_node' => $byNode, 'by_theme' => $byTheme, 'clusters' => $clusters];
    }

    /** Opens an internal incident for every cluster above the threshold that has none open yet. @return list<string> incident numbers opened */
    public function run(?int $days = null): array
    {
        $days ??= max(1, (int) config('onhost.chargeback.cluster_days', 30));
        $analytics = $this->analytics($days);
        $opened = [];
        $ctx = CommandContext::system('chargeback.analyst');
        foreach ($analytics['clusters'] as $cluster) {
            $existing = Incident::query()->where('meta->chargeback_cluster', $cluster['key'])->whereNotIn('state', [IncidentStateMachine::RESOLVED, IncidentStateMachine::POSTMORTEM])->exists();
            if ($existing) {
                continue;
            }
            $component = $this->componentFor($cluster, $analytics);
            $title = $cluster['kind'] === 'node' ? "Odchody zákazníků z uzlu {$cluster['label']} ({$cluster['count']}× za {$days} dní)" : "Odchody zákazníků z produktu {$cluster['label']} ({$cluster['count']}× za {$days} dní)";
            $incident = $this->incidents->open([
                'title' => $title, 'severity' => 'p3', 'components' => [$component], 'visibility' => 'internal', 'source' => 'chargeback', 'sla_relevant' => false,
                'impact' => "Nejčastější důvod: {$cluster['top_theme']}. Žádosti o vrácení kreditu se hromadí; prověřte příčinu (výkon uzlu, šablona, cena) dřív, než odejdou další.",
                'note' => 'Otevřeno automaticky z analytiky chargebacků (práh '.$analytics['threshold'].' žádostí za '.$days.' dní).',
                'meta' => ['chargeback_cluster' => $cluster['key'], 'count' => $cluster['count'], 'theme' => $cluster['top_theme']],
            ], $ctx);
            $opened[] = $incident->number;
            $this->outbox->publish(GenericEvent::of('chargeback.cluster', 'incident', $incident->id, ['number' => $incident->number, 'cluster' => $cluster['key'], 'count' => $cluster['count'], 'theme' => $cluster['top_theme'], 'label' => $cluster['label']]));
        }

        return $opened;
    }

    public function threshold(): int
    {
        return max(2, (int) config('onhost.chargeback.cluster_threshold', 3));
    }

    public static function theme(string $reason): string
    {
        foreach (self::THEMES as $theme => $pattern) {
            if ($reason !== '' && preg_match($pattern, $reason)) {
                return $theme;
            }
        }

        return 'other';
    }

    /** @param  array<string,int>  $themes */
    private static function top(array $themes): string
    {
        arsort($themes);

        return (string) (array_key_first($themes) ?? 'other');
    }

    /** @param  array<string,mixed>  $cluster @param  array<string,mixed>  $analytics */
    private function componentFor(array $cluster, array $analytics): string
    {
        $family = null;
        $region = null;
        if ($cluster['kind'] === 'node') {
            $row = collect($analytics['by_node'])->firstWhere('node_id', substr($cluster['key'], 5));
            $region = $row['region'] ?? null;
            $family = match ($row['role'] ?? '') {
                'game' => 'game', 'compute' => 'cloud', 'web' => 'web', default => null
            };
        } else {
            $row = collect($analytics['by_product'])->firstWhere('product_key', substr($cluster['key'], 8));
            $family = $row['family'] ?? null;
        }
        $keys = StatusComponent::query()->pluck('key')->all();
        $candidates = array_values(array_filter([
            $family && $region ? "{$family}s-{$region}" : null, $family && $region ? "{$family}-{$region}" : null,
            $family ? "{$family}s-".config('onhost.provisioning.default_region', 'cz1') : null, $family ? "{$family}-".config('onhost.provisioning.default_region', 'cz1') : null, $family,
        ]));
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $keys, true)) {
                return $candidate;
            }
        }

        return in_array('portal', $keys, true) ? 'portal' : (string) ($keys[0] ?? 'portal');
    }
}
