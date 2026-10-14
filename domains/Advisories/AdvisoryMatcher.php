<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories;

use Illuminate\Support\Collection;
use Onhost\Domain\Advisories\Models\Advisory;
use Onhost\Domain\Advisories\Models\AdvisoryCase;
use Onhost\Domain\Advisories\Models\AdvisoryMatch;
use Onhost\Domain\Advisories\Models\InventoryItem;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Advisories × inventory (SN-3). Every advisory range is compared with the items of the same ecosystem and product; a hit becomes a
 * match with its plan (RemediationPlanner), a match whose software is gone or no longer in the range is closed (`gone`). An advisory
 * with live matches gets a case, and staff hear about it once (`security.advisory.matched`, hot when it is critical or exploited).
 * A case closed earlier opens again when new places are hit; a dismissed one stays dismissed.
 */
final class AdvisoryMatcher
{
    private const LIVE = [AdvisoryMatch::OPEN, AdvisoryMatch::QUEUED, AdvisoryMatch::RUNNING, AdvisoryMatch::NOTIFIED, AdvisoryMatch::FAILED];

    public function __construct(private readonly OutboxPublisher $outbox) {}

    /** @return array{advisories:int, matches:int, cases_opened:int, gone:int} */
    public function run(): array
    {
        $index = [];
        foreach (InventoryItem::query()->get() as $item) {
            $index[$item->ecosystem.'|'.strtolower($item->product)][] = $item;
        }
        $stats = ['advisories' => 0, 'matches' => 0, 'cases_opened' => 0, 'gone' => 0];
        Advisory::query()->orderBy('id')->chunkById(200, function ($advisories) use ($index, &$stats): void {
            foreach ($advisories as $advisory) {
                $hits = [];
                foreach ((array) $advisory->affected as $range) {
                    foreach ($index[($range['ecosystem'] ?? '').'|'.strtolower((string) ($range['product'] ?? ''))] ?? [] as $item) {
                        $affects = AdvisoryVersion::affects($item->version, (array) $range);
                        if ($affects === false || (isset($hits[$item->id]) && $hits[$item->id]['confidence'] === 'exact')) {
                            continue;
                        }
                        $hits[$item->id] = ['item' => $item, 'confidence' => $affects === true ? 'exact' : 'probable', 'fixed' => isset($range['fixed']) ? (string) $range['fixed'] : null];
                    }
                }
                $existing = AdvisoryMatch::query()->where('advisory_id', $advisory->id)->get()->keyBy('inventory_id');
                if ($hits === [] && $existing->isEmpty()) {
                    continue;
                }
                $stats['advisories']++;
                $case = $this->apply($advisory, $hits, $existing, $stats);
                if ($case !== null) {
                    $stats['matches'] += $case->matches_count;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<string, array{item:InventoryItem, confidence:string, fixed:?string}>  $hits
     * @param  Collection<string, AdvisoryMatch>  $existing
     * @param  array<string,int>  $stats
     */
    private function apply(Advisory $advisory, array $hits, $existing, array &$stats): ?AdvisoryCase
    {
        $case = AdvisoryCase::query()->where('advisory_id', $advisory->id)->first();
        $fresh = 0;
        foreach ($hits as $itemId => $hit) {
            $item = $hit['item'];
            $match = $existing->get($itemId);
            $plan = RemediationPlanner::plan($item, $hit['fixed'], $hit['confidence']);
            if ($match === null || in_array($match->state, [AdvisoryMatch::GONE, AdvisoryMatch::FIXED, AdvisoryMatch::SKIPPED], true)) {
                $fresh++;
                $match ??= new AdvisoryMatch(['advisory_id' => $advisory->id, 'inventory_id' => $itemId]);
                $match->forceFill(['state' => AdvisoryMatch::OPEN, 'operation_id' => null, 'notified_at' => null]);
            }
            $match->forceFill([
                'subject_type' => $item->subject_type, 'subject_id' => $item->subject_id, 'organization_id' => $item->organization_id, 'ecosystem' => $item->ecosystem,
                'product' => $item->product, 'version' => $item->version, 'fixed_in' => $hit['fixed'], 'confidence' => $hit['confidence'],
            ]);
            if ($match->state === AdvisoryMatch::OPEN) { // a plan is fixed once it was approved
                $match->forceFill(['class' => $plan['class'], 'action' => $plan['action']]);
            }
            $match->save();
        }
        foreach ($existing as $itemId => $match) {
            if (! isset($hits[$itemId]) && in_array($match->state, [AdvisoryMatch::OPEN, AdvisoryMatch::QUEUED, AdvisoryMatch::NOTIFIED, AdvisoryMatch::FAILED], true)) {
                $match->forceFill(['state' => AdvisoryMatch::GONE])->save();
                $stats['gone']++;
            }
        }
        $live = AdvisoryMatch::query()->where('advisory_id', $advisory->id)->whereIn('state', self::LIVE)->count();
        if ($case === null && $live === 0) {
            return null;
        }
        if ($case === null) {
            $case = AdvisoryCase::query()->create(['advisory_id' => $advisory->id, 'state' => AdvisoryCase::NEW, 'severity' => $advisory->severity, 'kev' => $advisory->kev, 'matches_count' => $live, 'plan_revision' => 1, 'wave' => 0]);
            $stats['cases_opened']++;
            $this->announce($advisory, $case, $live);
        } elseif ($fresh > 0 && in_array($case->state, [AdvisoryCase::RESOLVED, AdvisoryCase::PARTIAL, AdvisoryCase::HALTED], true)) {
            $case->forceFill(['state' => AdvisoryCase::NEW, 'plan_revision' => $case->plan_revision + 1, 'resolved_at' => null, 'halted_reason' => null, 'wave' => 0])->save();
            $this->announce($advisory, $case, $live);
        } elseif ($fresh > 0 && $case->state === AdvisoryCase::AWAITING) {
            $case->forceFill(['plan_revision' => $case->plan_revision + 1])->save(); // somebody approving now approves what they see now
        }
        AdvisoryMatch::query()->where('advisory_id', $advisory->id)->whereNull('case_id')->update(['case_id' => $case->id]);
        $case->forceFill(['matches_count' => $live, 'severity' => $advisory->severity, 'kev' => $advisory->kev])->save();
        if ($live === 0 && $case->open() && $case->state !== AdvisoryCase::APPROVED) {
            $case->forceFill(['state' => AdvisoryCase::RESOLVED, 'resolved_at' => now(), 'decision_note' => $case->decision_note ?? 'Dotčený software už v zranitelné verzi neběží.'])->save();
        }

        return $case;
    }

    private function announce(Advisory $advisory, AdvisoryCase $case, int $live): void
    {
        $classes = AdvisoryMatch::query()->where('advisory_id', $advisory->id)->whereIn('state', self::LIVE)->pluck('class')->countBy()->all();
        $this->outbox->publish(GenericEvent::of('security.advisory.matched', 'advisory_case', $case->id, [
            'cve' => $advisory->key, 'title' => $advisory->title, 'severity' => $advisory->severity, 'kev' => $advisory->kev, 'matches' => $live, 'classes' => $classes, 'urgent' => $advisory->urgent(),
        ]));
    }
}
